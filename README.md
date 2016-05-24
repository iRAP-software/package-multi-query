# MultiQuery Package
This is a package for simplifying the sending multiple PHP Mysqli queries in a single request and handling the responses. This often greatly improve performance by removing the round-trip-time, which is most noticeable when the database is on a remote host.

## Example Usage

```
$db = new mysqli($host, $user, $password, $db_name);
$multiQuery = new iRAP\MultiQuery\MultiQuery($db);
$multiQuery->addQuery("DROP TABLE `table1`");
$multiQuery->addQuery("DROP TABLE `table2`");
$multiQuery->addQuery("DROP TABLE `table3`");
$multiQuery->run();
```

@TODO - Include example of getting the values/outputs.


## Transactions
This package also has a class to help with making MySQL transactions. Below is an example of using this class:

```
$transaction = new iRAP\MultiQuery\Transaction($mysqli);
$transaction->addQuery('DELETE FROM `myTable` WHERE id = ' . $id);
$transaction->addQuery('INSERT INTO `myTable` SELECT * FROM `myTable2` WHERE id = ' . $id);
$transaction->run();

if ($transaction->getStatus() !== iRAP\MultiQuery\Transaction::STATE_SUCCEEDED)
{
    throw new Exception("Failed to reset the record in countermeasures_model::interim_calcs");
}
```

The transaction will automatically detect and rollback if **any** of the queries within the transaction object fail. By default, if the transaction fails the object will not retry, but you can configure it to do so. Below is the same example, but this time we have set it to retry up to 5 times when the transaction is run, and to wait 3 seconds between each attempt. The default for the sleep period if you do not set it, is 1 second.

```
$transaction = new iRAP\MultiQuery\Transaction($mysqli, $attempts=5, $sleepPeriod=3);
$transaction->addQuery('DELETE FROM `myTable` WHERE id = ' . $id);
...
```
