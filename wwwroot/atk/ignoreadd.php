<?php
require_once __DIR__ . '/../_init.php';

authBasic(USER_ATK_MANAGER);

if (!isset($_POST['ip']) || !preg_match('/^([0-2]?\d{1,2}\.){3}([0-2]?\d{1,2})(\/(\d{1,2}))?$/', $_POST['ip'])) {
    http_response_code(400);
    die('Bad request');
}

$db = createDbLink();

$ip = $_POST['ip'];

$description = $_POST['description'] ?? '';
if (empty($description)) {
    $description = null;
}

pg_query_params($db, "insert into atkDbIgnoreList(net, description) values ($1, $2)", [$ip, $description]);

http_response_code(303);
header('Location: /atk/ignorelist.php');
closeDbLink($db);
die('OK');
