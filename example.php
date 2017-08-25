<?php

require_once(__DIR__ . '/MultiQuery.php');
require_once(__DIR__ . '/Transaction.php');


function run()
{
    $connection = new mysqli('database.irap-dev.org', 'root', 'hickory2000', 'sync_db');

    $multiQuery = new iRAP\MultiQuery\MultiQuery($connection);
    $select1QueryIndex = $multiQuery->addQuery('SELECT * FROM `table1`');
    $showTablesQueryIndex = $multiQuery->addQuery('SHOW TABLES`');
    $select2QueryIndex = $multiQuery->addQuery('SELECT * FROM `table2`');
    $multiQuery->run();
    
    if ($multiQuery->hasErrors())
    {
        $errors = $multiQuery->getErrors();
        
        // do something with the errors array such as use them in an exception message....
    }
    else 
    {
        $tablesResult = $multiQuery->getResult($showTablesQueryIndex);
    
        if ($tablesResult === FALSE)
        {
            throw new Exception("Failed to fetch tables");
        }
        else
        {
            $tables = array();
            
            while (($row = $tablesResult->fetch_array()) !== null)
            {
                $tables[] = $row[0];
            }
            
            print "tables: " . implode(", ", $tables);
        }
    }
        
    
    # // Example 2 - Fetch data from two tables that have exactly the same structure 
    # // e.g. a case of partitioning data using table names like "dataset1", "dataset2"
    $multiQuery2 = new iRAP\MultiQuery\MultiQuery($connection);
    $multiQuery2->addQuery('SELECT * FROM `table1`');
    $multiQuery2->addQuery('SELECT * FROM `table2`');
    $multiQuery2->run();
    $mergedResult = $multiQuery2->getMergedResult();
    print "merged result: " . print_r($mergedResult, true) . PHP_EOL;
}

run();
