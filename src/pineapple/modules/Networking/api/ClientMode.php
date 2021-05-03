<?php namespace helper;

class ClientMode
{

    public function scanForNetworks($interface, $uciID, $radio)
    {
        $interface = escapeshellarg($interface);
        if (substr($interface, -4, -1) === "mon") {
            if ($interface == "'wlan1mon'") {
                exec("/etc/init.d/pineapd stop");
            }
            exec("airmon-ng stop {$interface}");
            $interface = substr($interface, 0, -4) . "'";
            exec("iw dev {$interface} scan &> /dev/null");
        }

        $device = getDevice();
        if (uciGet("wireless.@wifi-iface[{$uciID}].network") === 'wwan') {
            uciSet("wireless.@wifi-iface[{$uciID}].network", 'lan');
            exec("wifi up $radio");
            sleep(2);
        }

        exec("iwinfo {$interface} scan", $apScan);

        if ($apScan[0] === 'No scan results') {
            return null;
        }

        $apArray = preg_split("/^Cell/m", implode("\n", $apScan));

        $returnArray = array();
        foreach ($apArray as $apData) {
            $apData = explode("\n", $apData);
            $accessPoint = array();
            $accessPoint['mac'] = substr($apData[0], -17);
            $accessPoint['ssid'] = substr(trim($apData[1]), 8, -1);
            if (mb_detect_encoding($accessPoint['ssid'], "auto") === false) {
                continue;
            }

            $base = $device == 'tetra' ? 23 : -2;
            $accessPoint['channel'] = intval(substr(trim($apData[2]), $base));

            $signalString = explode("  ", trim($apData[3]));
            $accessPoint['signal'] = substr($signalString[0], 8);
            $accessPoint['quality'] = substr($signalString[1], 9);

            $security = substr(trim($apData[4]), 12);
            if ($security === "none") {
                $accessPoint['security'] = "Open";
            } else {
                $accessPoint['security'] = $security;
            }

            if ($accessPoint['mac'] && trim($apData[1]) !== "ESSID: unknown") {
                array_push($returnArray, $accessPoint);
            }
        }

        return $returnArray;
    }

    public function connectToAP($uciID, $ap, $key, $radioID)
    {
        exec('[ ! -z "$(wifi config)" ] && wifi config > /etc/config/wireless');

        $security = $ap->security;
        switch ($security) {
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

        uciSet("wireless.@wifi-iface[{$uciID}].network", 'wwan');
        uciSet("wireless.@wifi-iface[{$uciID}].mode", 'sta');
        uciSet("wireless.@wifi-iface[{$uciID}].ssid", $ap->ssid);
        uciSet("wireless.@wifi-iface[{$uciID}].encryption", $encryption);
        uciSet("wireless.@wifi-iface[{$uciID}].key", $key);

        if ($radioID === false) {
            execBackground("wifi");
        } else {
            execBackground("wifi reload {$radioID}");
            execBackground("wifi up {$radioID}");
        }

        return array("success" => true);
    }

    public function checkConnection()
    {
        $connection = exec('iwconfig 2>&1 | grep ESSID:\"');
        if (trim($connection)) {
            $interface = explode(" ", $connection)[0];

            $ssidString = substr($connection, strpos($connection, 'ESSID:'));
            $ssid = substr($ssidString, 7, -1);
            $ip = exec("ifconfig " . escapeshellarg($interface) . " | grep -m 1 inet | awk '{print \$2}' | awk -F':' '{print \$2}'");


            return array("connected" => true, "interface" => $interface, "ssid" => $ssid, "ip" => $ip);
        } else {
            return array("connected" => false);
        }
    }

    public function disconnect($uciID, $radioID)
    {
        uciSet("wireless.@wifi-iface[{$uciID}].network", 'lan');
        uciSet("wireless.@wifi-iface[{$uciID}].ssid", '');
        uciSet("wireless.@wifi-iface[{$uciID}].encryption", 'none');
        uciSet("wireless.@wifi-iface[{$uciID}].key", '');

        if ($radioID === false) {
            execBackground("wifi");
        } else {
            execBackground("wifi reload {$radioID}");
            execBackground("wifi up {$radioID}");
        }

        return array("success" => true);
    }
}
