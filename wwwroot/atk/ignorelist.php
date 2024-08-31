<?php
require_once __DIR__ . '/../_init.php';

authBasic(USER_ATK_MANAGER);

$db = createDbLink();
$atk_list = pg_query($db, 'SELECT il.id, il.net, il.description FROM atkDbIgnoreList il');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attack Ignored IP List</title>
    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="/styles/table.css">
</head>

<body>
    <h1>Attack Ignored IP List</h1>

    <section>
        <h2>Add to list</h2>
        <form method="POST" action="ignoreadd.php">
            <div>
                <label for="add-net">Network</label>
            </div>
            <div>
                <input type="text" id="add-net" name="ip">
            </div>
            <div>
                <label for="add-description">Description</label>
            </div>
            <div>
                <input type="text" id="add-description" name="description">
            </div>
            <div>
                <input type="submit" value="Add">
            </div>
        </form>
    </section>

    <table class="border">
        <thead>
            <tr>
                <th>CIDR</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < pg_num_rows($atk_list); $i++) : ?>
                <?php $rows = pg_fetch_array($atk_list, NULL, PGSQL_ASSOC); ?>
                <tr>
                    <td><?= htmlentities($rows['net'])?></td>
                    <td><?= htmlentities($rows['description'])?></td>
                    <td>
                        <form action="ignoredel.php" method="post">
                            <input type="hidden" name="id" value="<?= $rows['id'] ?>">
                            <input type="submit" value="Delete">
                        </form>
                    </td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    <?php require __DIR__ . '/../_legal.php'; ?>
    <script src="../scripts/global.js"></script>
</body>

</html>
<?php
closeDbLink($db);
?>
