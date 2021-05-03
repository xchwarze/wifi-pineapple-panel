#!/usr/bin/php-cgi -q
<?php namespace pineapple;

include_once('/pineapple/api/DatabaseConnection.php');

abstract class EncryptionFields
{
    const WPA = 0x01;
    const WPA2 = 0x02;
    const WEP = 0x04;
    const WPA_PAIRWISE_WEP40 = 0x08;
    const WPA_PAIRWISE_WEP104 = 0x10;
    const WPA_PAIRWISE_TKIP = 0x20;
    const WPA_PAIRWISE_CCMP = 0x40;
    const WPA2_PAIRWISE_WEP40 = 0x80;
    const WPA2_PAIRWISE_WEP104 = 0x100;
    const WPA2_PAIRWISE_TKIP = 0x200;
    const WPA2_PAIRWISE_CCMP = 0x400;
    const WPA_AKM_PSK = 0x800;
    const WPA_AKM_ENTERPRISE = 0x1000;
    const WPA_AKM_ENTERPRISE_FT = 0x2000;
    const WPA2_AKM_PSK = 0x4000;
    const WPA2_AKM_ENTERPRISE = 0x8000;
    const WPA2_AKM_ENTERPRISE_FT = 0x10000;
    const WPA_GROUP_WEP40 = 0x20000;
    const WPA_GROUP_WEP104 = 0x40000;
    const WPA_GROUP_TKIP = 0x80000;
    const WPA_GROUP_CCMP = 0x100000;
    const WPA2_GROUP_WEP40 = 0x200000;
    const WPA2_GROUP_WEP104 = 0x400000;
    const WPA2_GROUP_TKIP = 0x800000;
    const WPA2_GROUP_CCMP = 0x1000000;
}

function printEncryption($encryptionType)
{
    $retStr = '';
    if ($encryptionType === 0) {
        return 'Open';
    }
    if ($encryptionType & EncryptionFields::WEP) {
        return 'WEP';
    } else if (($encryptionType & EncryptionFields::WPA) && ($encryptionType & EncryptionFields::WPA2)) {
        $retStr .= 'WPA Mixed ';
    } else if ($encryptionType & EncryptionFields::WPA) {
        $retStr .= 'WPA ';
    } else if ($encryptionType & EncryptionFields::WPA2) {
        $retStr .= 'WPA2 ';
    }
    if (($encryptionType & EncryptionFields::WPA2_AKM_PSK) || ($encryptionType & EncryptionFields::WPA_AKM_PSK)) {
        $retStr .= 'PSK ';
    } else if (($encryptionType & EncryptionFields::WPA2_AKM_ENTERPRISE) || ($encryptionType & EncryptionFields::WPA_AKM_ENTERPRISE)) {
        $retStr .= 'Enterprise ';
    } else if (($encryptionType & EncryptionFields::WPA2_AKM_ENTERPRISE_FT) || ($encryptionType & EncryptionFields::WPA_AKM_ENTERPRISE_FT)) {
        $retStr .= 'Enterprise FT ';
    }
    $retStr .= '(';
    if (($encryptionType & EncryptionFields::WPA2_PAIRWISE_CCMP) || ($encryptionType & EncryptionFields::WPA_PAIRWISE_CCMP)) {
        $retStr .= 'CCMP ';
    }
    if (($encryptionType & EncryptionFields::WPA2_PAIRWISE_TKIP) || ($encryptionType & EncryptionFields::WPA_PAIRWISE_TKIP)) {
        $retStr .= 'TKIP ';
    }
    if (($encryptionType & EncryptionFields::WPA2_PAIRWISE_WEP40) || ($encryptionType & EncryptionFields::WPA_PAIRWISE_WEP40)) {
        $retStr .= 'WEP40 ';
    }
    if (($encryptionType & EncryptionFields::WPA2_PAIRWISE_WEP104) || ($encryptionType & EncryptionFields::WPA_PAIRWISE_WEP104)) {
        $retStr .= 'WEP104 ';
    }
    $retStr = substr($retStr, 0, -1);
    $retStr .= ')';
    return $retStr;
}

if (count($argv) < 2) {
	exit("Usage: ${argv[0]} [scan id]\n");
}

$scanID = intval($argv[1]);
$scanDBPath = exec("uci get pineap.@config[0].recon_db_path");
if (!file_exists($scanDBPath)) {
	exit("File ${scanDBPath} does not exist\n");
}

$dbConnection = new DatabaseConnection($scanDBPath);
if ($dbConnection === NULL) {
	exit("Unable to create database connection\n");
}

if (isset($dbConnection->error['databaseConnectionError'])) {
	exit($dbConnection->strError() . "\n");
}

$data = array();
$data[$scanID] = array();
$aps = $dbConnection->query("SELECT scan_id, ssid, bssid, encryption, hidden, channel, signal, wps, last_seen FROM aps WHERE scan_id='%d';", $scanID);
foreach ($aps as $ap_row) {
    $data[$scanID]['aps'][$ap_row['bssid']] = array();
    $data[$scanID]['aps'][$ap_row['bssid']]['ssid'] = $ap_row['ssid'];
    $data[$scanID]['aps'][$ap_row['bssid']]['encryption'] = printEncryption($ap_row['encryption']);
    $data[$scanID]['aps'][$ap_row['bssid']]['hidden'] = $ap_row['hidden'];
    $data[$scanID]['aps'][$ap_row['bssid']]['channel'] = $ap_row['channel'];
    $data[$scanID]['aps'][$ap_row['bssid']]['signal'] = $ap_row['signal'];
    $data[$scanID]['aps'][$ap_row['bssid']]['wps'] = $ap_row['wps'];
    $data[$scanID]['aps'][$ap_row['bssid']]['last_seen'] = $ap_row['last_seen'];
    $data[$scanID]['aps'][$ap_row['bssid']]['clients'] = array();
    $clients = $dbConnection->query("SELECT scan_id, mac, bssid, last_seen FROM clients WHERE scan_id='%d' AND bssid='%s';", $ap_row['scan_id'], $ap_row['bssid']);
    foreach ($clients as $client_row) {
        $data[$scanID]['aps'][$ap_row['bssid']]['clients'][$client_row['mac']] = array();
        $data[$scanID]['aps'][$ap_row['bssid']]['clients'][$client_row['mac']]['bssid'] = $client_row['bssid'];
        $data[$scanID]['aps'][$ap_row['bssid']]['clients'][$client_row['mac']]['last_seen'] = $client_row['last_seen'];
    }
}

$data[$scanID]['outOfRangeClients'] = array();
$clients = $dbConnection->query("
    SELECT t1.mac, t1.bssid, t1.last_seen FROM clients t1
    LEFT JOIN aps t2 ON
    t2.bssid = t1.bssid WHERE t2.bssid IS NULL AND
    t1.bssid != 'FF:FF:FF:FF:FF:FF' COLLATE NOCASE AND t1.scan_id='%d';
    ", $client_row['scan_id']);

foreach ($clients as $client_row) {
    $data[$scanID]['outOfRangeClients'][$client_row['mac']] = array();
    $data[$scanID]['outOfRangeClients'][$client_row['mac']] = $client_row['bssid'];
}

$data[$scanID]['unassociatedClients'] = array();
$clients = $dbConnection->query("SELECT mac FROM clients WHERE bssid='FF:FF:FF:FF:FF:FF' COLLATE NOCASE;");

foreach ($clients as $client_row) {
    array_push($data[$scanID]['unassociatedClients'], $client_row['mac']);
}

file_put_contents("php://stdout", json_encode($data, JSON_PRETTY_PRINT));