#!/usr/bin/php-cgi
<?php namespace pineapple;

require_once('../../../api/DatabaseConnection.php');

$probesOnly = false;

if (count($argv) > 1) {
    if ($argv[1] === "--probes") {
        $probesOnly = true;
    }
}

$logDBPath = exec("uci get pineap.@config[0].hostapd_db_path");
if (!file_exists($logDBPath)) {
	exit("File ${logDBPath} does not exist\n");
}
$dbConnection = new DatabaseConnection($logDBPath);
if ($dbConnection === NULL) {
	exit("Unable to create database connection\n");
}
if (isset($dbConnection->error['databaseConnectionError'])) {
	exit($dbConnection->strError() . "\n");
}

$sql = "SELECT * FROM log ORDER BY updated_at DESC;";
if ($probesOnly) {
    $sql = "SELECT * FROM log WHERE log_type=0 ORDER BY updated_at DESC;";
}
$log = $dbConnection->query($sql);

$clearlog = exec('uci get reporting.@settings[0].clear_log');
if ($clearlog == '1') {
	$dbConnection->exec('DELETE FROM log;');
}
echo json_encode($log, JSON_PRETTY_PRINT);
