<?php

/*
 * Class to help with performing transactions.
 * This will allow a user to specify a bunch of queries
 * they wish to execute, send them all off at once, and then check that none of them failed.
 * If they failed, then the transaction will be automatically rolled back, if they ALL succeed,
 * then the transaction will be committed.
 *
 * You do not (and should not) specify any of START_TRANSACTION, BEGIN, COMMIT, or ROLLBACK. This
 * class takes care of that for you.
 *
 * This will submit the START_TRANSACTION, then all of the queries, then the commit/rollback, so
 * there are always 3 sequential queries to the database, which is much faster than submitting
 * each query on its own. If any transaction querys fail then the rollback command is sent and
 * status is set to STATE_FAILED.
 *
 * WARNING
 *  - This class does NOT check whether any of the queries being added cause an implicit commit.
 *    This means that if one of your queries was to create, drop, or alter a table, the
 *    transaction up to that point will be committed even if there were errors.
 *    For more information please refer to:
 *    http://dev.mysql.com/doc/refman/5.6/en/implicit-commit.html
 *
 * - This class does not check that you are using an engine that supports transactions, e.g.
 *   InnoDB rather than MyISAM
 *
 */

namespace Programster\MultiQuery;

class Transaction
{
    private \mysqli $m_connection;
    private MultiQuery $m_multiQuery;
    private array $m_queries = array();
    private TransactionStatus $m_status = TransactionStatus::NOT_APPLICABLE;
    private int $m_retryAttempts;
    private int $m_retrySleepPeriod;


    /**
     * Create the transaction object for sending mysqli transactions.
     * @param \mysqli $mysqli - the database connection to send the transaction on.
     * @param int $retryAttempts - the number of times to retry a transaction if it fails.
     * @param int $retrySleepPeriod - the number of seconds to sleep between retry attempts
     */
    public function __construct(\mysqli $mysqli, array $queries, int $retryAttempts=0, int $retrySleepPeriod=1)
    {
        $this->m_queries = $queries;
        $this->m_connection = $mysqli;
        $this->m_retryAttempts = $retryAttempts;
        $this->m_retrySleepPeriod = $retrySleepPeriod;
        $this->run();
    }


    /**
     * After having added all the queries for the transaction, call this method to execute
     * the transaction.
     * This will attempt to execute the query
     */
    private function run()
    {
        $attemptsLeft = $this->m_retryAttempts;

        do
        {
            $this->runAttempt();
            $attemptsLeft--;

            if ($this->m_status !== TransactionStatus::SUCCEEDED)
            {
                $warning_msg = "Transaction failed.";
                trigger_error($warning_msg, E_USER_WARNING);

                if ($attemptsLeft > 0)
                {
                    sleep($this->m_retrySleepPeriod);
                }
            }
        } while ($this->m_status !== TransactionStatus::SUCCEEDED && $attemptsLeft > 0);
    }


    /**
     * Try to execute the transaction. This is a single iteration called within the run() method.
     * @throws \Exception
     */
    private function runAttempt()
    {
        while (!$this->m_connection->begin_transaction())
        {
            trigger_error("Failed to start transaction. Retrying after 1 second.", E_USER_WARNING);
            sleep(1);
        }

        try
        {
            $this->m_multiQuery = new MultiQuery($this->m_connection, $this->m_queries);
            $results = $this->m_multiQuery->getResults();
            $queriesSucceeded = true;
        }
        catch (\Exception $ex)
        {
            // there were errors in the multi query so there are no results. loop over errors
            // instead.
            $queriesSucceeded = false;
        }

        if ($queriesSucceeded && count($results) != count($this->m_queries))
        {
            # Safety check, should not happen if there were no errors
            $errMsg = "Transaction number of results [" . count($results) . "] does not equal "
                    . "the number of queries [" . count($this->m_queries) . "] so rolling back.";

            print $errMsg . PHP_EOL;

            print "results: " . print_r($results, true);
            trigger_error($errMsg, E_USER_WARNING);
            $queriesSucceeded = false;
        }

        if (!$queriesSucceeded)
        {
            $this->m_connection->rollback();
            $this->m_status = TransactionStatus::FAILED;
        }
        else
        {
            if ($this->m_connection->commit())
            {
                $this->m_status = TransactionStatus::SUCCEEDED;
            }
            else
            {
                $this->m_connection->rollback();
                $this->m_status = TransactionStatus::FAILED;
            }
        }
    }


    /**
     * Return the multi query object that this class used to send the transactional queries.
     * If the developer wants the results of their queries, they should fetch this object and
     * get the results from it.
     * @return MultiQuery
     */
    public function getMultiQueryObject() : MultiQuery
    {
        return $this->m_multiQuery;
    }


    /**
     * The opposite of hasErrors just so the programmer
     * can write the code whichever way they like.
     */
    public function wasSuccessful() : bool
    {
        return ($this->m_status === TransactionStatus::SUCCEEDED);
    }


    /**
     * Get the queries that this transaction used.
     * Useful to have for debugging when a transaction fails.
     * @return array - the array of query strings.
     */
    public function getQueries() : array { return $this->m_queries; }
}
