<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once(__DIR__ . '/MultiQuery.php');
require_once(__DIR__ . '/Transaction.php');


$host = "localhost";
$user = "root";
$password = "hickory2000";
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

$transaction = new iRAP\MultiQuery\Transaction($mysqli);
$transaction->addQuery("INSERT INTO `Persons` SET `FirstName`='Joe', `LastName`='Smith'");
$transaction->addQuery("INSERT INTO `Persons` SET `FirstName`='Samantha', `LastName`='Smith'");
$transaction->run();


$transaction = new iRAP\MultiQuery\Transaction($mysqli);
$transaction->addQuery("SELECT * FROM `Persons`");
$transaction->addQuery("SELECT * FROM `Persons`");
$transaction->run();

$results = $transaction->getMultiQueryObject()->get_merged_result();
print_r($results);


if ($transaction->getStatus() === \iRAP\MultiQuery\Transaction::STATE_SUCCEEDED)
{
   print "transaction succeeded.";
}
else
{
    print "transaction failed.";
}

$dropQuery = "DROP TABLE `Persons`";
$mysqli->query($dropQuery);


