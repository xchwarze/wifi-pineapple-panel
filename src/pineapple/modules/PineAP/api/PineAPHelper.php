<?php namespace pineapple;

class PineAPHelper
{
    public function getSetting($settingKey)
    {
        $configFile = file_get_contents("/tmp/pineap.conf");

        $configFile = explode("\n", $configFile);
        foreach($configFile as $row => $data) {
            $entry = str_replace(" ", "", $data);
            $entry = explode("=", $entry);

            if ($entry[0] == $settingKey) {
                if ($entry[1] == 'on') {
                    return true;
                } elseif ($entry[1] == 'off') {
                    return false;
                } else {
                    return $entry[1];
                }
            }
        }

        return false;
    }

    public function setSetting($settingKey, $settingVal)
    {
        $configFile = file_get_contents("/tmp/pineap.conf");
        $configFileOut = "";

        $configFile = explode("\n", $configFile);
        foreach($configFile as $row => $data) {
            $entry = str_replace(" ", "", $data);
            $entry = explode("=", $entry);

            if ($entry[0] == $settingKey) {
                $entry[1] = $settingVal;
            }

            if ($entry[0] != "" && $entry[1] != "") {
                $configFileOut .= $entry[0] . " = " . $entry[1] . "\n";
            }
        }

        file_put_contents("/tmp/pineap.conf", "");
        file_put_contents("/tmp/pineap.conf", $configFileOut);

        return true;
    }

    public function enableAssociations()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec("pineap /tmp/pineap.conf karma on");
        } else {
            $this->setSetting("karma", "on");
        }

        return true;
    }

    public function disableAssociations()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec("pineap /tmp/pineap.conf karma off");
        } else {
            $this->setSetting("karma", "off");
        }

        return true;
    }

    public function enablePineAP()
    {
        exec('/etc/init.d/pineapd start');
        return true;
    }

    public function disablePineAP()
    {
        exec('/etc/init.d/pineapd stop');
        return true;
    }

    public function enableLogging()
    {
        if (\helper\checkRunning('/usr/sbin/pineapd')) {
            exec("pineap /tmp/pineap.conf logging on");
        } else {
            $this->setSetting("logging", "on");
        }

        return true;
    }

    public function disableLogging()
    {
        $this->setSetting("logging", "off");
        if (\helper\checkRunning('/usr/sbin/pineapd')) {
            exec("pineap /tmp/pineap.conf logging off");
        }
        return true;
    }

    public function enableBeaconer()
    {
        $this->setSetting("broadcast_ssid_pool", "on");
        if (\helper\checkRunning('/usr/sbin/pineapd')) {
            exec('pineap /tmp/pineap.conf broadcast_pool on');
        }
        return true;
    }

    public function disableBeaconer()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec('pineap /tmp/pineap.conf broadcast_pool off');
        } else {
            $this->setSetting("broadcast_ssid_pool", "off");
        }
        return true;
    }

    public function enableResponder()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec('pineap /tmp/pineap.conf beacon_responses on');
        } else {
            $this->setSetting("beacon_responses", "on");
        }
        return true;
    }

    public function disableResponder()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec('pineap /tmp/pineap.conf beacon_responses off');
        } else {
            $this->setSetting("beacon_responses", "off");
        }
        return true;
    }

    public function enableHarvester()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec('pineap /tmp/pineap.conf capture_ssids on');
        } else {
            $this->setSetting("capture_ssids", "on");
        }
        return true;
    }

    public function disableHarvester()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec('pineap /tmp/pineap.conf capture_ssids off');
        } else {
            $this->setSetting("capture_ssids", "off");
        }
        return true;
    }

    public function enableConnectNotifications()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec('pineap /tmp/pineap.conf connect_notifications on');
        } else {
            $this->setSetting("connect_notifications", "on");
        }
        return true;
    }

    public function disableConnectNotifications()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec('pineap /tmp/pineap.conf connect_notifications off');
        } else {
            $this->setSetting("connect_notifications", "off");
        }
        return true;
    }

    public function enableDisconnectNotifications()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec('pineap /tmp/pineap.conf disconnect_notifications on');
        } else {
            $this->setSetting("disconnect_notifications", "on");
        }
        return true;
    }

    public function disableDisconnectNotifications()
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            exec('pineap /tmp/pineap.conf disconnect_notifications off');
        } else {
            $this->setSetting("disconnect_notifications", "off");
        }
        return true;
    }

    public function getTarget()
    {
        return $this->getSetting("target_mac");
    }

    public function getSource()
    {
        return $this->getSetting("pineap_mac");
    }

    public function setBeaconInterval($interval)
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            $interval = escapeshellarg($interval);
            exec("pineap /tmp/pineap.conf beacon_interval {$interval}");
        } else {
            $this->setSetting("beacon_interval", "{$interval}");
        }

        return;
    }

    public function setResponseInterval($interval)
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            $interval = escapeshellarg($interval);
            exec("pineap /tmp/pineap.conf beacon_response_interval {$interval}");
        } else {
            $this->setSetting("beacon_response_interval", "{$interval}");
        }

        return;
    }

    public function setSource($mac)
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            $mac = escapeshellarg($mac);
            exec("pineap /tmp/pineap.conf set_source {$mac}");
        } else {
            $this->setSetting("pineap_mac", "{$mac}");
        }

        return;
    }

    public function setTarget($mac)
    {
        if (\helper\checkRunning("/usr/sbin/pineapd")) {
            $mac = escapeshellarg($mac);
            exec("pineap /tmp/pineap.conf set_target {$mac}");
        } else {
            $this->setSetting("target_mac", "{$mac}");
        }
        return;
    }

    public function deauth($target, $source, $channel, $multiplier = 1)
    {
        $channel = str_pad($channel, 2, "0", STR_PAD_LEFT);
        exec("pineap /tmp/pineap.conf deauth $source $target $channel $multiplier");
        return true;
    }
}
