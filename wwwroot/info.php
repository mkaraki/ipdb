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

    $atkDb = pg_query($link, "SELECT ip, extract(epoch from addedat) as addedat, extract(epoch from lastseen) as lastseen FROM atkIps WHERE ip = '$ip'");
    $inAtkDb = pg_num_rows($atkDb) > 0;
    if ($inAtkDb) {
        $atkDbData = pg_fetch_array($atkDb, NULL, PGSQL_ASSOC);
    }
    
    $meta_rdns_data = getReverseDnsInfo($link, $ip);

    closeDbLink($link);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/main.css">
    <title><?= $ip ?> - IPdb Search</title>
</head>

<body>
    <h1>IPdb <small>search</small></h1>
    <?php if ($isPrivate) : ?>
        <p>IP address <code><?= $ip ?></code> is private or blocked address.</p>
    <?php elseif ($inAtkDb) : ?>
        <p>IP address <code><?= $ip ?></code> found in following databases.</p>

        <?php if ($meta_rdns_data !== null) : ?>
            <h2>rDNS Cache</h2>
            <table>
                <tbody>
                    <tr>
                        <th scope="row">rDNS</th>
                        <td><?= $meta_rdns_data['rdns'] ?? '<i>No PTR or invalid record</i>' ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Last checked</th>
                        <td class="unixepoch" data-epoch="<?= $meta_rdns_data['last_checked'] ?>"><?= strDate($meta_rdns_data['last_checked']) ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

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
    <?php require __DIR__ . '/_legal.php'; ?>
</body>

</html>