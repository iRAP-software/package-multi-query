<?php

/* 
 * A quick script to test this package.
 */

require_once(__DIR__ . '/MultiQuery.php');
require_once(__DIR__ . '/Transaction.php');

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
    $host = "database.irap-dev.org";
    $user = "";
    $password = "";
    $database = "test";
    
    $mysqli = new \mysqli($host, $user, $password, $database);
    
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
    $insertTransaction = new iRAP\MultiQuery\Transaction($mysqli);
    $insertTransaction->addQuery("INSERT INTO `Persons` SET `FirstName`='Joe', `LastName`='Smith'");
    $insertTransaction->addQuery("INSERT INTO `Persons` SET `FirstName`='Samantha', `LastName`='Smith'");
    $insertTransaction->run();
    
    $selectTransaction = new iRAP\MultiQuery\Transaction($mysqli);
    $selectTransaction->addQuery("SELECT * FROM `Persons`");
    $selectTransaction->addQuery("SELECT * FROM `Persons`");
    $selectTransaction->run();

    $results = $selectTransaction->getMultiQueryObject()->getMergedResult();
    
    if ($selectTransaction->getStatus() === \iRAP\MultiQuery\Transaction::STATE_SUCCEEDED)
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
    $multiQuery = new iRAP\MultiQuery\MultiQuery($mysqli);
    
    $multiQuery->addQuery('SELECT * FROM `Persons`');
    $multiQuery->addQuery('bad query');
    $multiQuery->addQuery('SHOW TABLES');
    
    $multiQuery->run();
    
    if ($multiQuery->getStatus() === iRAP\MultiQuery\MultiQuery::STATE_ERRORS)
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
    $multiQuery = new iRAP\MultiQuery\MultiQuery($mysqli);
    
    $select1QueryIndex    = $multiQuery->addQuery('SELECT * FROM `Persons`');
    $showTablesQueryIndex = $multiQuery->addQuery('SHOW TABLES');
    $select2QueryIndex    = $multiQuery->addQuery('SELECT * FROM `Persons`');
    
    $multiQuery->run();
    
    if ($multiQuery->getStatus() === iRAP\MultiQuery\MultiQuery::STATE_SUCCEEDED)
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