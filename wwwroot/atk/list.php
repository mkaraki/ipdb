<?php
require_once __DIR__ . '/../_init.php';

$p = intval($_GET['p'] ?? 1);
$offset = ($p - 1) * 100;

$db = createDbLink();
$atk_list = pg_query($db, 'SELECT a.ip, meta_rdns.rdns as rdns,
                                extract(epoch from a.addedat) as addedat,
                                extract(epoch from a.lastseen) as lastseen
                            FROM
                                atkIps a
                            LEFT JOIN meta_rdns ON a.ip = meta_rdns.ip
                            ORDER BY lastseen DESC
                            LIMIT 100 OFFSET ' . $offset);
$atkIpCnt = pg_fetch_result(pg_query($db, 'SELECT COUNT(*) FROM atkIps'), 0, 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attack Detected IP List</title>
    <link rel="stylesheet" href="/styles/table.css">
</head>

<body>
    <?php if (isset($_GET['pj_status']) && is_numeric($_GET['pj_status']) && isset($_GET['pj_msg'])) : ?>
        <div>
            Last operation ends with code <?= $_GET['pj_status'] ?>: <?= htmlentities($_GET['pj_msg']) ?>
        </div>
    <?php endif; ?>
    <h1>Attack Detected IP List</h1>
    <table class="border">
        <thead>
            <tr>
                <th>IP (FQDN)</th>
                <th>First report</th>
                <th>Last report</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < pg_num_rows($atk_list); $i++) : ?>
                <?php $rows = pg_fetch_array($atk_list, NULL, PGSQL_ASSOC); ?>
                <tr>
                    <td><?= $rows['ip'] ?>
                        <?php if ($rows['rdns'] !== null) : ?>
                            (<?= $rows['rdns'] ?>)
                        <?php endif; ?>
                    </td>
                    <td class="unixepoch" data-epoch="<?= $rows['addedat'] ?>"><?= date('Y-m-d H:i:s a', $rows['addedat']) ?></td>
                    <td class="unixepoch" data-epoch="<?= $rows['lastseen'] ?>"><?= date('Y-m-d H:i:s a', $rows['addedat']) ?></td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    <div>
        <div>
            Page <?= $p ?> (<?= pg_num_rows($atk_list) ?> rows in page, <?= $atkIpCnt ?> rows in DB)
        </div>
        <div>
            <?php if ($p > 1) : ?>
                <a href="?p=<?= $p - 1 ?>">Prev</a>
            <?php endif; ?>
            <?php if (pg_num_rows($atk_list) >= 100) : ?>
                <a href="?p=<?= $p + 1 ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <script src="../scripts/global.js"></script>
</body>

</html>
<?php
closeDbLink($db);
?>