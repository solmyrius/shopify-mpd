<?php
require_once __DIR__ . '/env_config.php';
require_once __DIR__ . '/db/mysqli.lib.php';

$df = new db_factory($_ENV['mysql_host'], $_ENV['mysql_user'], $_ENV['mysql_pass'], $_ENV['mysql_dbname']);
$db = $df->new_db();

?>