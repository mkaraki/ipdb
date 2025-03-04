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
    <link rel="stylesheet" href="../styles/main.css">
    <title>IPdb ATK Database</title>
</head>

<body>
    <header>
        <h1>IPdb ATK Database</h1>
        <p><?= $atkIpCnt ?> IPs in list. <a href="list.php">See full list</a></p>
    </header>
    <section>
        <h2>Stats</h2>

        <table>
            <tbody>
                <?php foreach([1, 7, 14, 30, 60, 180, 365] as $day) : ?>
                    <?php
                        $dayStats = pg_query($link, "SELECT COUNT(*) FROM atkIps WHERE lastseen >= date_trunc('day', (now() AT TIME ZONE 'UTC') - interval '$day days')");
                        $dayCount = pg_fetch_result($dayStats, 0, 0);
                    ?>
                    <tr>
                        <th scope="row">Last <?= $day ?> days</th>
                        <td><?= number_format($dayCount) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <section>
            <h3>Country (last 30 days)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Country</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $countryStats = pg_query($link, 'SELECT ccode, COUNT(*) as cnt FROM atkIps WHERE lastseen >= now() - interval \'30 days\' GROUP BY ccode ORDER BY cnt DESC LIMIT 10');
                    ?>
                    <?php for ($i = 0; $i < pg_num_rows($countryStats); $i++) : ?>
                        <?php $row = pg_fetch_array($countryStats, NULL, PGSQL_ASSOC); ?>
                        <tr>
                            <th scope="row"><?= $i + 1 ?></th>
                            <?php if ($row['ccode'] === null) : ?>
                                <td>Unknown</td>
                            <?php else : ?>
                                <td class="countrycode" data-ccode="<?= htmlspecialchars($row['ccode']) ?>">
                                    <?= htmlentities($row['ccode']) ?>
                                </td>
                            <?php endif; ?>
                            <td><?= number_format($row['cnt']) ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </section>
        <section>
            <h3>ASN (Last 30 days)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>ASN</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $asnStats = pg_query($link, 'SELECT asn, COUNT(*) as cnt FROM atkIps WHERE lastseen >= now() - interval \'30 days\' GROUP BY asn ORDER BY cnt DESC LIMIT 10');
                    ?>
                    <?php for ($i = 0; $i < pg_num_rows($asnStats); $i++) : ?>
                        <?php $row = pg_fetch_array($asnStats, NULL, PGSQL_ASSOC); ?>
                        <tr>
                            <th scope="row"><?= $i + 1 ?></th>
                            <?php if ($row['asn'] === null) : ?>
                                <td>Unknown</td>
                            <?php else : ?>
                                <td><?= htmlentities($row['asn']) ?></td>
                            <?php endif; ?>
                            <td><?= number_format($row['cnt']) ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </section>
    </section>
    <?php require __DIR__ . '/../_legal.php'; ?>
    <script src="../scripts/global.js"></script>
    <script>
        rewriteEpoch();
        rewriteCountryCode();
    </script>
</body>

</html>
