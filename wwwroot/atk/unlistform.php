<?php
require_once __DIR__ . '/../_init.php';
authBasic(USER_ATK_MANAGER);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/main.css">
    <title>Unlist IP from ATK DB</title>
</head>

<body>
    <h1>Unlist IP Info (ATK)</h1>
    <form action="unlist.php" method="POST">
        <div>
            <label for="ip">IP Address</label>
            <input type="text" name="ip" id="ip" required>
        </div>
        <div>
            <input type="submit" value="Post">
        </div>
    </form>
    <?php require __DIR__ . '/../_legal.php'; ?>
</body>

</html>