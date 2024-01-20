<?php
require_once __DIR__ . '/_init.php';

if (!isset($_GET['q'])) {
    http_response_code(400);
    die('Bad request');
}

$ip = $_GET['q'];

if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    die('Non valid IP address');
}

$isPrivate = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
//$ipFamily = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'ipv6' : 'ipv4';

if (!$isPrivate) {

    $link = createDbLink();
    $atkDb = pg_query($link, "SELECT ip, rdns, extract(epoch from addedat) as addedat, extract(epoch from lastseen) as lastseen FROM atkIps WHERE ip = '$ip'");
    $inAtkDb = pg_num_rows($atkDb) > 0;
    if ($inAtkDb) {
        $atkDbData = pg_fetch_array($atkDb, NULL, PGSQL_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $ip ?> - IPdb Search</title>
</head>

<body>
    <h1>IPdb <small>search</small></h1>
    <?php if ($isPrivate) : ?>
        <p>IP address <code><?= $ip ?></code> is private or blocked address.</p>
    <?php elseif ($inAtkDb) : ?>
        <p>IP address <code><?= $ip ?></code> found in following databases.</p>

        <?php if ($inAtkDb) : ?>
            <h2>ATKdb</h2>
            <table>
                <tbody>
                    <tr>
                        <th scope="row">First seen</th>
                        <td class="unixepoch" data-epoch="<?= $atkDbData['addedat'] ?>"><?= strDate($atkDbData['addedat']) ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Last seen</th>
                        <td class="unixepoch" data-epoch="<?= $atkDbData['lastseen'] ?>"><?= strDate($atkDbData['lastseen']) ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
        <script src="../scripts/global.js"></script>
    <?php else : ?>
        <p>IP address <code><?= $ip ?></code> not found in our databases.</p>
    <?php endif; ?>
</body>

</html>