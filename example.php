<?php

$connection = new mysqli('localhost', 'user', 'password', 'database_name');
$multiQuery = new MultiQuery($connection);
$multiQuery->addQuery('SELECT * FROM `table1`');
$multiQuery->addQuery('SELECT * FROM `table2`');
$multiQuery->run();
$mergedResult = $multiQuery->get_merged_result();
var_dump($mergedResult);