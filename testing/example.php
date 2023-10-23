<?php

require_once(__DIR__ . '/settings.php');
require_once(__DIR__ . '/../src/MultiQuery.php');
require_once(__DIR__ . '/../src/Transaction.php');


function run()
{
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    $queries = array(
        'SELECT * FROM `Persons`',
        'SHOW TABLES',
        'SELECT * FROM `Persons`'
    );

    $multiQuery = new Programster\MultiQuery\MultiQuery($connection, $queries);


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
    $partitionedTablesQueries = array(
        'SELECT * FROM `table1`',
        'SELECT * FROM `table2`'
    );

    $multiQuery2 = new Programster\MultiQuery\MultiQuery($connection, $partitionedTablesQueries);
    $mergedResult = $multiQuery2->getMergedResult();
    print "merged result: " . print_r($mergedResult, true) . PHP_EOL;
}

run();
