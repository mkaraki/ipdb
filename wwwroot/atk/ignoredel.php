<?php
require_once __DIR__ . '/../_init.php';

authBasic(USER_ATK_MANAGER);

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    die('Bad request');
}

$db = createDbLink();

pg_query_params($db, "DELETE FROM atkDbIgnoreList WHERE id = $1", [$_POST['id']]);

http_response_code(303);
header('Location: /atk/ignorelist.php');
closeDbLink($db);
die('OK');
