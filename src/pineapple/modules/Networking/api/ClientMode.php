<?php namespace helper;

class ClientMode
{
    public function scanForNetworks($interface, $uciID, $radio)
    {
        $interface = escapeshellarg($interface);
        if (substr($interface, -4, -1) === "mon") {
            $pineapInterface = uciGet("pineap.@config[0].pineap_interface");
            if ($interface === "'{$pineapInterface}'") {
                exec("/etc/init.d/pineapd stop");
            }
            exec("airmon-ng stop {$interface}");
            $interface = substr($interface, 0, -4) . "'";
            exec("iw dev {$interface} scan &> /dev/null");
        }

        if (uciGet("wireless.@wifi-iface[{$uciID}].network") === 'wwan') {
            uciSet("wireless.@wifi-iface[{$uciID}].network", 'lan');
            exec("wifi up $radio");
            sleep(2);
        }

        exec("iwinfo {$interface} scan", $apScan);
        if ($apScan[0] === 'No scan results') {
            return null;
        }

        $returnArray = [];
        $apArray = preg_split("/^Cell/m", implode("\n", $apScan));
        foreach ($apArray as $apData) {
            $apData = explode("\n", $apData);
            $ssid = substr(trim($apData[1]), 8, -1);
            if (!$ssid || mb_detect_encoding($ssid, "auto") === false || $ssid === "unknown") {
                continue;
            }

            $channelString = explode("  ", trim($apData[2]));
            $signalString = explode("  ", trim($apData[3]));
            $security = substr(trim($apData[4]), 12);

            $returnArray[] = [
                'mac' => substr($apData[0], -17),
                'ssid' => $ssid,
                'channel' => intval(substr($channelString[1], 9)),
                'signal' => substr($signalString[0], 8),
                'quality' => substr($signalString[1], 9),
                'security' => ($security === "none") ? "Open" : $security,
            ];
        }

        return $returnArray;
    }

    public function connectToAP($uciID, $ap, $key, $radioID)
    {
        exec('[ ! -z "$(wifi config)" ] && wifi config >> /etc/config/wireless');

        switch ($ap->security) {
            case 'Open':
                $encryption = "none";
                break;
            case 'WPA (TKIP)':
            case 'WPA PSK (TKIP)':
                $encryption = "psk+tkip";
                break;
            case 'WPA (CCMP)':
            case 'WPA PSK (CCMP)':
                $encryption = "psk+ccmp";
                break;
            case 'WPA (TKIP, CCMP)':
            case 'WPA PSK (TKIP, CCMP)':
                $encryption = "psk+tkip+ccmp";
                break;
            case 'WPA2 (TKIP)':
            case 'WPA2 PSK (TKIP)':
                $encryption = "psk2+tkip";
                break;
            case 'WPA2 (CCMP)':
            case 'WPA2 PSK (CCMP)':
                $encryption = "psk2+ccmp";
                break;
            case 'WPA2 (TKIP, CCMP)':
            case 'WPA2 PSK (TKIP, CCMP)':
                $encryption = "psk2+ccmp+tkip";
                break;
            case 'mixed WPA/WPA2 (TKIP)':
            case 'mixed WPA/WPA2 PSK (TKIP)':
                $encryption = "psk-mixed+tkip";
                break;
            case 'mixed WPA/WPA2 (CCMP)':
            case 'mixed WPA/WPA2 PSK (CCMP)':
                $encryption = "psk-mixed+ccmp";
                break;
            case 'mixed WPA/WPA2 (TKIP, CCMP)':
            case 'mixed WPA/WPA2 PSK (TKIP, CCMP)':
                $encryption = "psk-mixed+ccmp+tkip";
                break;
            default:
                $encryption = "";
        }

        uciSet("wireless.@wifi-iface[{$uciID}].network", 'wwan', false);
        uciSet("wireless.@wifi-iface[{$uciID}].mode", 'sta', false);
        uciSet("wireless.@wifi-iface[{$uciID}].ssid", $ap->ssid, false);
        uciSet("wireless.@wifi-iface[{$uciID}].encryption", $encryption, false);
        uciSet("wireless.@wifi-iface[{$uciID}].key", $key, false);
        uciCommit();

        if ($radioID === false) {
            execBackground("wifi");
        } else {
            execBackground("wifi reload {$radioID}");
            execBackground("wifi up {$radioID}");
        }

        return ["success" => true];
    }

    public function checkConnection()
    {
        $connection = exec('iwconfig 2>&1 | grep ESSID:\"');
        if (trim($connection)) {
            $interface = explode(" ", $connection)[0];

            $ssidString = substr($connection, strpos($connection, 'ESSID:'));
            $ssid = substr($ssidString, 7, -1);
            $ip = exec("ifconfig " . escapeshellarg($interface) . " | grep -m 1 inet | awk '{print \$2}' | awk -F':' '{print \$2}'");

            return ["connected" => true, "interface" => $interface, "ssid" => $ssid, "ip" => $ip];
        }

        return ["connected" => false];
    }

    public function disconnect($uciID, $radioID)
    {
        uciSet("wireless.@wifi-iface[{$uciID}].network", 'lan', false);
        uciSet("wireless.@wifi-iface[{$uciID}].ssid", '', false);
        uciSet("wireless.@wifi-iface[{$uciID}].encryption", 'none', false);
        uciSet("wireless.@wifi-iface[{$uciID}].key", '', false);
        uciCommit();

        if ($radioID === false) {
            execBackground("wifi");
        } else {
            execBackground("wifi reload {$radioID}");
            execBackground("wifi up {$radioID}");
        }

        return ["success" => true];
    }
}
