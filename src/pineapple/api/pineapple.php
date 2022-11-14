<?php namespace helper;

function execBackground($command)
{
    exec("echo \"{$command}\" | /usr/bin/at now", $var);
    return $var;
}

function checkDependency($dependencyName)
{
    exec("/usr/bin/which $dependencyName", $output);
    return !(trim($output[0]) === "");
}

function isSDAvailable()
{
    $output = exec('/bin/mount | /bin/grep "on /sd" -c');
    return $output >= 1;
}

function sdReaderPresent() {
    return file_exists('/sd');
}

function sdCardPresent() {
    return !file_exists('/sd/NO_SD');
}

function checkRunning($processName)
{
    $processName = escapeshellarg($processName);
    exec("/usr/bin/pgrep {$processName}", $output);
    return count($output) > 0;
}

function checkRunningFull($processString) {
    $processString = escapeshellarg($processString);
    exec("/usr/bin/pgrep -f {$processString}", $output);
    return count($output) > 0;
}

function udsSend($path, $message)
{
    $sock = stream_socket_client("unix://{$path}", $errno, $errstr);
    fwrite($sock, $message);
    fclose($sock);
    return true;
}

function dgramUdsSend($path, $message)
{
    $sock = NULL;
    if(!($sock = socket_create(AF_UNIX, SOCK_DGRAM, 0))) {
        return false;
    }
    socket_sendto($sock, $message, strlen($message), 0, $path);
    return true;
}

function uciGet($uciString, $autoBool = true)
{
    $uciString = escapeshellarg($uciString);
    $result = exec("uci get {$uciString}");
    if ($autoBool && ($result === "0" || $result === "1")) {
        return $result === "1";
    }

    return $result;
}

function uciSet($settingString, $value)
{
    $settingString = escapeshellarg($settingString);
    if (!empty($value)) {
        $value = escapeshellarg($value);
    }

    if ($value === "''" || $value === "") {
        $value = "'0'";
    }

    exec("uci set {$settingString}={$value}");
    exec("uci commit {$settingString}");
}

function uciAddList($settingString, $value)
{
    $settingString = escapeshellarg($settingString);
    if (!empty($value)) {
        $value = escapeshellarg($value);
    }

    if ($value === "''") {
        $value = "'0'";
    }

    exec("uci add_list {$settingString}={$value}");
    exec("uci commit {$settingString}");
}

function downloadFile($file)
{
    $token = hash('sha256', $file . time());

    require_once('DatabaseConnection.php');
    $database = new \pineapple\DatabaseConnection("/etc/pineapple/pineapple.db");
    $database->exec("CREATE TABLE IF NOT EXISTS downloads (token VARCHAR NOT NULL, file VARCHAR NOT NULL, time timestamp default (strftime('%s', 'now')));");
    $database->exec("INSERT INTO downloads (token, file) VALUES ('%s', '%s')", $token, $file);

    return $token;
}

function getFirmwareVersion()
{
    return trim(file_get_contents('/pineapple/pineapple_version'));
}

function getDevice()
{
    $data = file_get_contents('/proc/cpuinfo');
    if (preg_match('/NANO/', $data)) {
        return 'nano';
    } elseif (preg_match('/TETRA/', $data)) {
        return 'tetra';
    }
    return 'unknown';
}

function getMacFromInterface($interface)
{
    $interface = escapeshellarg($interface);
    return trim(exec("ifconfig {$interface} | grep HWaddr | awk '{print $5}'"));
}

function getBoard()
{
    $data = file_get_contents('/tmp/sysinfo/board_name');
    if (!empty($data)) {
        return str_replace(',', '_', trim($data));
    }

    return false;
}
