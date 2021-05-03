<?php namespace pineapple;

class Configuration extends SystemModule
{
    public function route()
    {
        switch ($this->request->action) {
            case 'getCurrentTimeZone':
                $this->getCurrentTimeZone();
                break;

            case 'getLandingPageData':
                $this->getLandingPageData();
                break;

            case 'saveLandingPage':
                $this->saveLandingPageData();
                break;

            case 'changePass':
                $this->changePass();
                break;

            case 'changeTimeZone':
                $this->changeTimeZone();
                break;

            case 'resetPineapple':
                $this->resetPineapple();
                break;

            case 'haltPineapple':
                $this->haltPineapple();
                break;

            case 'rebootPineapple':
                $this->rebootPineapple();
                break;

            case 'getLandingPageStatus':
                $this->getLandingPageStatus();
                break;

            case 'getAutoStartStatus':
                $this->getAutoStartStatus();
                break;

            case 'enableLandingPage':
                $this->enableLandingPage();
                break;

            case 'disableLandingPage':
                $this->disableLandingPage();
                break;

            case 'enableAutoStart':
                $this->enableAutoStart();
                break;

            case 'disableAutoStart':
                $this->disableAutoStart();
                break;

            case 'getButtonScript':
                $this->getButtonScript();
                break;

            case 'saveButtonScript':
                $this->saveButtonScript();
                break;

            case 'getDevice':
                $this->getDeviceName();
                break;
        }
    }

    private function haltPineapple()
    {
        $this->execBackground("sync && led all off && halt");
        $this->response = array("success" => true);
    }

    private function rebootPineapple()
    {
        $this->execBackground("reboot");
        $this->response = array("success" => true);
    }

    private function resetPineapple()
    {
        if ($this->getDevice() === "nano") {
            $this->execBackground("mtd -r erase rootfs_data");
        } else if ($this->getDevice() === "tetra") {
            $this->execBackground("jffs2reset -y && reboot &");
        }
        $this->response = array("success" => true);
    }

    private function getCurrentTimeZone()
    {
        $currentTimeZone = exec('date +%Z%z');
        $this->response = array("currentTimeZone" => $currentTimeZone);
    }

    private function changeTimeZone()
    {
        $timeZone = $this->request->timeZone;
        file_put_contents('/etc/TZ', $timeZone);
        $this->uciSet('system.@system[0].timezone', $timeZone);
        $this->response = array("success" => true);
    }

    private function getLandingPageData()
    {
        $landingPage = file_get_contents('/etc/pineapple/landingpage.php');
        $this->response = array("landingPage" => $landingPage);
    }

    private function getLandingPageStatus()
    {
        if (!empty(exec("iptables -L -vt nat | grep 'www to:.*:80'"))) {
            $this->response = array("enabled" => true);
            return;
        }
        $this->response = array("enabled" => false);
    }

    private function enableLandingPage()
    {
        exec('iptables -t nat -A PREROUTING -p tcp --dport 80 -j DNAT --to-destination $(uci get network.lan.ipaddr):80');
        exec('iptables -t nat -A POSTROUTING -j MASQUERADE');
        copy('/pineapple/modules/Configuration/api/landingpage_index.php', '/www/index.php');
        $this->response = array("success" => true);
    }

    private function disableLandingPage()
    {
        @unlink('/www/index.php');
        exec('iptables -t nat -D PREROUTING -p tcp --dport 80 -j DNAT --to-destination $(uci get network.lan.ipaddr):80');
        $this->response = array("success" => true);
    }

    private function getAutoStartStatus()
    {
        if($this->uciGet("landingpage.@settings[0].autostart") == 1) {
            $this->response = array("enabled" => true);
        } else {
            $this->response = array("enabled" => false);
        }
    }

    private function enableAutoStart()
    {
        $this->uciSet("landingpage.@settings[0].autostart", "1");
        $this->response = array("success" => true);
    }

    private function disableAutoStart()
    {
        $this->uciSet("landingpage.@settings[0].autostart", "0");
        $this->response = array("success" => true);
    }

    private function saveLandingPageData()
    {
        if (file_put_contents('/etc/pineapple/landingpage.php', $this->request->landingPageData) !== false) {
            $this->response = array("success" => true);
        } else {
            $this->error = "Error saving Landing Page.";
        }
    }

    private function getButtonScript()
    {
        if (file_exists('/etc/pineapple/button_script')) {
            $script = file_get_contents('/etc/pineapple/button_script');
            $this->response = array("buttonScript" => $script);
        } else {
            $this->error = "The button script does not exist.";
        }
    }

    private function saveButtonScript()
    {
        if (file_exists('/etc/pineapple/button_script')) {
            file_put_contents('/etc/pineapple/button_script', $this->request->buttonScript);
            $this->response = array("success" => true);
        } else {
            $this->error = "The button script does not exist.";
        }
    }

    private function getDeviceName()
    {
        $this->response = array("device" => $this->getDevice());
    }

    protected function changePass()
    {
        if ($this->request->newPassword === $this->request->newPasswordRepeat) {
            if (parent::changePassword($this->request->oldPassword, $this->request->newPassword) === true) {
                $this->response = array("success" => true);
                return;
            }
        }

        $this->response = array("success" => false);
    }
}