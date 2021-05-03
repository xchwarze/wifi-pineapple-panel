<?php namespace helper;

class AccessPoint
{

    public function saveAPConfig($apConfig)
    {
        uciSet('wireless.radio0.channel', $apConfig->selectedChannel);
        $device = getDevice();
        if ($apConfig->selectedChannel > 14 && $device == 'tetra') {
            uciSet('wireless.radio0.hwmode', '11n');
        }

        uciSet('wireless.@wifi-iface[0].ssid', $apConfig->openSSID);
        uciSet('wireless.@wifi-iface[0].hidden', $apConfig->hideOpenAP);
        uciSet('wireless.@wifi-iface[0].maxassoc', $apConfig->maxClients);
        uciSet('wireless.@wifi-iface[1].ssid', $apConfig->managementSSID);
        uciSet('wireless.@wifi-iface[1].key', $apConfig->managementKey);
        uciSet('wireless.@wifi-iface[1].disabled', $apConfig->disableManagementAP);
        uciSet('wireless.@wifi-iface[1].hidden', $apConfig->hideManagementAP);
        execBackground('wifi');
        return array("success" => true);
    }

    public function getAPConfig()
    {
        exec("iwinfo phy0 freqlist", $output);
        preg_match_all("/\(Channel (\d+)\)$/m", implode("\n", $output), $channelList);

        // Remove radar detection channels
        $channels = array();
        foreach ($channelList[1] as $channel) {
            if ((int)$channel < 52 || (int)$channel > 140) {
                $channels[] = $channel;
            }
        }

        return array(
            "selectedChannel" => uciGet("wireless.radio0.channel"),
            "availableChannels" => $channels,
            "openSSID" => uciGet("wireless.@wifi-iface[0].ssid"),
            "maxClients" => uciGet("wireless.@wifi-iface[0].maxassoc"),
            "hideOpenAP" => uciGet("wireless.@wifi-iface[0].hidden"),
            "managementSSID" => uciGet("wireless.@wifi-iface[1].ssid"),
            "managementKey" => uciGet("wireless.@wifi-iface[1].key"),
            "disableManagementAP" => uciGet("wireless.@wifi-iface[1].disabled"),
            "hideManagementAP" => uciGet("wireless.@wifi-iface[1].hidden")
        );
    }
}
