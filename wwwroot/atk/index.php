<?php
require_once __DIR__ . '/../_init.php';
$link = createDbLink();
$atkIpCnt = pg_fetch_result(pg_query($link, 'SELECT COUNT(*) FROM atkIps'), 0, 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPdb ATK Database</title>
</head>

<body>
    <header>
        <h1>IPdb ATK Database</h1>
        <p><?= $atkIpCnt ?> IPs in list. <a href="list.php">See full list</a></p>
    </header>
</body>

</html>