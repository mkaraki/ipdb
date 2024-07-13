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
    <title>Post IP Info</title>
</head>

<body>
    <h1>Post IP Info (ATK)</h1>
    <a href="list.php">List</a> | <a href="batchpostform.php">Batch add</a>
    <hr />
    <form action="post.php" method="POST">
        <input type="hidden" name="role_mgr" value="1">
        <div>
            <label for="ip">IP Address</label>
            <input type="text" name="ip" id="ip" required>
        </div>
        <br />
        <div>
            <label for="loggedat">Logged at (Unix Timestamp):</label>
            <input type="number" name="loggedat" id="loggedat" oninput="logdtChg()" onchange="logdtChg()">
            <div>
                Logged at (Browser locale):
                <span id="loggedat-preview">NA</span>
            </div>
        </div>
        <div>
            <div>
                <label for="tsc-lg-fg">FortiGate logged event time:</label>
                <input type="number" id="tsc-lg-fg">
                <button type="button" onclick="logdtFromNano(document.getElementById('tsc-lg-fg'))">Convert</button>
            </div>
        </div>
        <br /><br />
        <div>
            <input type="submit" value="Post">
        </div>
    </form>
    <?php require __DIR__ . '/../_legal.php'; ?>
    <script src="../scripts/global.js"></script>
    <script>
        const logdtElem = document.getElementById('loggedat');

        function logdtChg() {
            document.getElementById('loggedat-preview').innerText = (new Date(logdtElem.value * 1000)).toLocaleString();
        }

        function logdtFromNano(elem) {
            document.getElementById('loggedat').value = nanoToSeconds(elem.value);
            logdtChg();
        }
    </script>
</body>

</html>