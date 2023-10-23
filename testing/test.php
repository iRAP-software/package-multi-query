<?php

/*
 * A quick script to test this package.
 */

require_once(__DIR__ . '/settings.php');
require_once(__DIR__ . '/../src/MultiQuery.php');
require_once(__DIR__ . '/../src/Transaction.php');


function run()
{
    $db = init();
    transactionTest($db);
    badQueryTest($db);
    goodMultiQueryTest($db);
    cleanUp($db);
}


function init()
{
    $mysqli = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    $createQuery =
        "CREATE TABLE `Persons` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `FirstName` varchar(255) NOT NULL,
          `LastName` varchar(255) NOT NULL,
          `LastUpdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1";

    $mysqli->query($createQuery);
    return $mysqli;
}


/**
 * Test that our transactions that wrap around multi queries works.
 */
function transactionTest($mysqli)
{
    $insertQueries = array(
        "INSERT INTO `Persons` SET `FirstName`='Joe', `LastName`='Smith'",
        "INSERT INTO `Persons` SET `FirstName`='Samantha', `LastName`='Smith'",
    );

    $insertTransaction = new Programster\MultiQuery\Transaction($mysqli, $insertQueries);


    $selectQueries = array(
        "SELECT * FROM `Persons`",
        "SELECT * FROM `Persons`",
    );

    $selectTransaction = new Programster\MultiQuery\Transaction($mysqli, $selectQueries);
    $results = $selectTransaction->getMultiQueryObject()->getMergedResult();

    if ($selectTransaction->wasSuccessful())
    {
       print "Transaction test: PASSED" . PHP_EOL;
    }
    else
    {
        print "Transaction test: FAILED"  . PHP_EOL;
    }
}


/**
 * Test that we handle the multi query handling it when the user enters an erroneous SQL query
 * which messes up the entire multi query.
 * @param mysqli $mysqli
 */
function badQueryTest(mysqli $mysqli)
{
    $queries = array(
        'SELECT * FROM `Persons`',
        'bad query',
        'SHOW TABLES',
    );

    $multiQuery = new Programster\MultiQuery\MultiQuery($mysqli, $queries);

    if ($multiQuery->hasErrors())
    {
        try
        {
            $result = $multiQuery->getResult(1);

            # if we got here without get_result throwing an exception the test failed.
            print "badQueryTest:  FAILED" . PHP_EOL;
        }
        catch (Exception $ex)
        {
            print "badQueryTest: PASSED" . PHP_EOL;
        }
    }
    else
    {
        print "badQueryTest:  FAILED" . PHP_EOL;
    }
}


function goodMultiQueryTest(mysqli $mysqli)
{
    $queries = array(
        'SELECT * FROM `Persons`',
        'SHOW TABLES',
        'SELECT * FROM `Persons`'
    );

    $multiQuery = new Programster\MultiQuery\MultiQuery($mysqli, $queries);
    $showTablesQueryIndex = 1;

    if ($multiQuery->wasSuccessful())
    {
        $tablesResult = $multiQuery->getResult($showTablesQueryIndex);

        if ($tablesResult === FALSE)
        {
            print "goodMultiQueryTest: FAILED" . PHP_EOL;
        }
        else
        {
            $tables = array();

            while (($row = $tablesResult->fetch_array()) !== null)
            {
                $tables[] = $row[0];
            }

            if (count($tables) == 1 && $tables[0] === "Persons")
            {
                print "goodMultiQueryTest: PASSED" . PHP_EOL;
            }
            else
            {
                print "goodMultiQueryTest: FAILED" . PHP_EOL;
            }
        }
    }
    else
    {
        print "goodMultiQueryTest: FAILED" . PHP_EOL;
    }
}


function cleanUp($mysqli)
{
    # clean up.
    $dropQuery = "DROP TABLE `Persons`";
    $mysqli->query($dropQuery);
}

run();
