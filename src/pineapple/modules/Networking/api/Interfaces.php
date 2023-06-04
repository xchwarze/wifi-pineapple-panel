<?php namespace helper;

class Interfaces
{
    public function getMacData()
    {
        $macData = [];
        exec("ifconfig -a | grep wlan | awk '{print \$1\" \"\$5}'", $interfaceArray);
        foreach ($interfaceArray as $interface) {
            $interface = explode(" ", $interface);
            $macData[$interface[0]] = $interface[1];
        }

        return array_reverse($macData);
    }

    public function getUciID($interface)
    {
        $interfaceNumber = str_replace("wlan", "", $interface);
        if ($interfaceNumber === "0") {
            return 0;
        } elseif ($interfaceNumber === "0-1") {
            return 1;
        } elseif ($interfaceNumber === "0-2") {
            return 2;
        }

        return (intval($interfaceNumber) + 2);
    }

    public function getRadioID($interface)
    {
        exec('wifi status', $wifiStatus);
        $radioArray = json_decode(implode("\n", $wifiStatus));
        foreach ($radioArray as $radio => $radioConfig) {
            if (isset($radioConfig->interfaces[0]->config->ifname) &&
                $radioConfig->interfaces[0]->config->ifname === $interface) {
                return $radio;
            }
        }

        return false;
    }

    public function setMac($random, $interface, $newMac, $forceReload)
    {
        $uciID = $this->getUciID($interface);
        $interface = escapeshellarg($interface);

        if ($random) {
            $mac = exec("ifconfig {$interface} down && macchanger -A {$interface} | grep New | awk '{print \$3}'");
        } else {
            $requestMac = escapeshellarg($newMac);
            $mac = exec("ifconfig {$interface} down && macchanger -m {$requestMac} {$interface} | grep New | awk '{print \$3}'");
        }

        uciSet("wireless.@wifi-iface[{$uciID}].macaddr", $mac);
        if ($forceReload) {
            exec("wifi");
        } else {
            exec("ifconfig {$interface} up");
        }

        return ["success" => true, "uci" => $uciID];
    }

    public function resetMac($interface)
    {
        $uciID = $this->getUciID($interface);
        exec("uci set wireless.@wifi-iface[{$uciID}].macaddr=''");
        exec("wifi");
        return ["success" => true];
    }

    public function resetWirelessConfig()
    {
        execBackground("wifi config > /etc/config/wireless && wifi");
        return ["success" => true];
    }

    public function getInterfaceList()
    {
        exec("ifconfig -a | grep encap:Ethernet | awk '{print \$1\",\"\$5}'", $interfaceArray);
        return $interfaceArray;
    }

    public function getClientInterfaces()
    {
        $clientInterfaces = [];
        exec("ifconfig -a | grep wlan | awk '{print \$1}'", $interfaceArray);
        foreach ($interfaceArray as $interface) {
            if (\DeviceConfig::HIDE_WLAN0_CLIENT && substr($interface, 0, 5) === "wlan0") {
                continue;
            }
            $clientInterfaces[] = $interface;
        }

        return array_reverse($clientInterfaces);
    }
}
