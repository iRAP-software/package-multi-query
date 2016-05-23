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
 *  - This class does NOT check whether any of the queries being added cause an implit commit.
 *    This means that if one of your queries was to create, drop, or alter a table, the 
 *    transaction up to that point will be committed even if there were errors.
 *    For more information please refer to: 
 *    http://dev.mysql.com/doc/refman/5.6/en/implicit-commit.html
 * 
 * - This class does not check that you are using an engine that supports transactions, e.g.
 *   InnoDB rather than MyISAM
 * 
 */

namespace iRAP\MultiQuery;

class Transaction
{
    const STATE_NOT_APPLICABLE = 0; # no transaction has been sent
    const STATE_SUCCEEDED = 1; # transaction sent and succeeded
    const STATE_FAILED = 2; # transaction sent but had to be rolled back.
    
    /* @var $m_connection \mysqli */
    private $m_connection;
    private $m_multiQuery;
    private $m_queries = array();
    private $m_status = self::STATE_NOT_APPLICABLE;
    private $m_retryAttempts;
    private $m_retrySleepPeriod;
    
    /**
     * Create the transaction object for sending mysqli transactions.
     * @param \mysqli $mysqli - the database connection to send the transaction on.
     * @param int $retryAttempts - the number of times to retry a transaction if it fails.
     * @param int $retrySleepPeriod - the number of seconds to sleep between retry attempts
     */
    public function __construct($mysqli, $retryAttempts=0, $retrySleepPeriod=1)
    {
        $this->m_connection = $mysqli;
        $this->m_retryAttempts = $retryAttempts;
        $this->m_retrySleepPeriod = $retrySleepPeriod;
    }
    
    
    public function addQuery($query)
    {
        $this->m_queries[] = $query;
    }
    
    
    /**
     * After having added all the queries for the transaction, call this method to execute
     * the transaction.
     * This will attempt to execute the query
     */
    public function run()
    {
        $attempts_left = $this->m_retryAttempts;
        
        do
        {
            $this->runAttempt();
            $attempts_left--;
            
            if ($this->m_status !== self::STATE_SUCCEEDED)
            {
                $warning_msg = "Transaction failed.";
                trigger_error($warning_msg, E_USER_WARNING);
                
                if ($attempts_left > 0)
                {
                    sleep($this->m_retrySleepPeriod);
                }
            }
        } while ($this->m_status !== self::STATE_SUCCEEDED && $attempts_left > 0);
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
        
        $this->m_multiQuery = new MultiQuery($this->m_connection);
        
        foreach ($this->m_queries as $query)
        {
            $this->m_multiQuery->addQuery($query);
        }
        
        $this->m_multiQuery->run();        
        $results = $this->m_multiQuery->get_results();
        
        $queriesSucceeded = true;
        
        # Safety check, should not happen.
        if (count($results) != count($this->m_queries))
        {
            $errMsg = "Transaction number of results [" . count($results) . "] does not equal the number of queries [" . count($this->m_queries) . "].";
            throw new \Exception($errMsg);
        }
        
        foreach ($results as $result)
        {
            if ($result === FALSE)
            {
                $queriesSucceeded = false;
                break;
            }
        }
        
        if (!$queriesSucceeded)
        {
            $this->m_connection->rollback();
            $this->m_status = self::STATE_FAILED;
        }
        else
        {
            if ($this->m_connection->commit())
            {
                $this->m_status = self::STATE_SUCCEEDED;
            }
            else
            {
                $this->m_connection->rollback();
                $this->m_status = self::STATE_FAILED;
            }
        }
    }
    
    
    /**
     * Return the multi query object that this class used to send the transactional queries.
     * If the developer wants the results of their queries, they should fetch this object and
     * get the results from it.
     * @return MultiQuery
     */
    public function getMultiQueryObject() 
    {
        return $this->m_multiQuery;
    }
    
    
    public function getStatus() { return $this->m_status; }
}

