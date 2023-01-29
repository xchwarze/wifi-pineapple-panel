<?php namespace helper;

class AccessPoint
{
    public function saveAPConfig($apConfig)
    {
        if (is_array($apConfig)) {
            $apConfig = (object)$apConfig;
        }

        uciSet('wireless.radio0.channel', $apConfig->selectedChannel);

        uciSet('wireless.@wifi-iface[0].ssid', $apConfig->openSSID, false);
        uciSet('wireless.@wifi-iface[0].disabled', $apConfig->disableOpenAP, false);
        uciSet('wireless.@wifi-iface[0].hidden', $apConfig->hideOpenAP, false);
        uciSet('wireless.@wifi-iface[0].maxassoc', $apConfig->maxClients, false);

        uciSet('wireless.@wifi-iface[1].ssid', $apConfig->managementSSID, false);
        uciSet('wireless.@wifi-iface[1].key', $apConfig->managementKey, false);
        uciSet('wireless.@wifi-iface[1].disabled', $apConfig->disableManagementAP, false);
        uciSet('wireless.@wifi-iface[1].hidden', $apConfig->hideManagementAP, false);

        uciCommit();
        execBackground('wifi');

        return ["success" => true];
    }

    public function getAPConfig($getChannelInfo = true)
    {
        $channels = [];
        if ($getChannelInfo) {
            exec("iwinfo phy0 freqlist", $output);
            preg_match_all("/\(Channel (\d+)\)$/m", implode("\n", $output), $channelList);

            // Remove radar detection channels
            foreach ($channelList[1] as $channel) {
                if ((int)$channel < 52 || (int)$channel > 140) {
                    $channels[] = $channel;
                }
            }
        }

        return [
            "selectedChannel" => uciGet("wireless.radio0.channel"),
            "availableChannels" => $channels,

            "openSSID" => uciGet("wireless.@wifi-iface[0].ssid"),
            "maxClients" => uciGet("wireless.@wifi-iface[0].maxassoc", false),
            "disableOpenAP" => uciGet("wireless.@wifi-iface[0].disabled"),
            "hideOpenAP" => uciGet("wireless.@wifi-iface[0].hidden"),

            "managementSSID" => uciGet("wireless.@wifi-iface[1].ssid"),
            "managementKey" => uciGet("wireless.@wifi-iface[1].key"),
            "disableManagementAP" => uciGet("wireless.@wifi-iface[1].disabled"),
            "hideManagementAP" => uciGet("wireless.@wifi-iface[1].hidden")
        ];
    }
}
