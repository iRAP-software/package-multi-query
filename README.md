# MultiQuery Package
This is a package for simplifying the sending multiple PHP Mysqli queries in a single request and handling the responses. This often greatly improve performance by removing the round-trip-time, which is most noticeable when the database is on a remote host.

## Example Usage

### Basic Example
```
$db = new mysqli($host, $user, $password, $db_name);
$multiQuery = new iRAP\MultiQuery\MultiQuery($db);
$multiQuery->addQuery("DROP TABLE `table1`");
$multiQuery->addQuery("DROP TABLE `table2`");
$multiQuery->addQuery("DROP TABLE `table3`");
$multiQuery->run();
```

### Full Example
In the example below we run lots of different queries and use the index we get when we added the 
query in order to get it's result from the multiQuery object later. We also demonstrate how to
check that nothing went wrong by checking the status of the object after having run it.

```
$connection = new mysqli('host', 'user', 'password', 'db_name');

$multiQuery = new iRAP\MultiQuery\MultiQuery($connection);
$select1QueryIndex = $multiQuery->addQuery('SELECT * FROM `table1`');
$showTablesQueryIndex = $multiQuery->addQuery('SHOW TABLES`');
$select2QueryIndex = $multiQuery->addQuery('SELECT * FROM `table2`');
$multiQuery->run();

if ($multiQuery->getStatus() === iRAP\MultiQuery\MultiQuery::STATE_SUCCEEDED)
{
    $errors = $multiQuery->get_errors();
    // do something with the errors array such as use them in an exception message....
}
else 
{
    $tablesResult = $multiQuery->get_result($showTablesQueryIndex);

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
```

### Merged Result Example
If you have partitioned your data using separate tables (e.g. the tables all have the same
structure), then you may want to make use of the get_merged_result() method to just stitch
all the query results into one
```
# // Example 2 - Fetch data from two tables that have exactly the same structure 
# // e.g. a case of partitioning data using table names like "dataset1", "dataset2"
$multiQuery2 = new iRAP\MultiQuery\MultiQuery($connection);
$multiQuery2->addQuery('SELECT * FROM `table1`');
$multiQuery2->addQuery('SELECT * FROM `table2`');
$multiQuery2->run();
$mergedResult = $multiQuery2->get_merged_result();
print "merged result: " . print_r($mergedResult, true) . PHP_EOL;
```


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