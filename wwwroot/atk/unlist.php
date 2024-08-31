<?php
require_once __DIR__ . '/../_init.php';

authBasic(USER_ATK_MANAGER);

$db = createDbLink();

if (!isset($_POST['ip']) || !filter_var($_POST['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
    http_response_code(400);
    die('Bad request');
}

$ip = $_POST['ip'];

pg_query_params($db, 'DELETE FROM atkIps WHERE ip = $1', [$ip]);

http_response_code(303);
header('Location: /atk/list.php');
closeDbLink($db);
die('OK');
