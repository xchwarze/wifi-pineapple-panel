<?php namespace pineapple;

require_once('/pineapple/modules/PineAP/api/PineAPHelper.php');

class PineAP extends SystemModule
{
    const EAP_USER_FILE = "/etc/pineape/hostapd-pineape.eap_user";

    private $pineAPHelper;
    private $dbConnection;

    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->pineAPHelper = new PineAPHelper();
        $this->dbConnection = false;

        $dbLocation = $this->uciGet("pineap.@config[0].ssid_db_path");
        if (file_exists($dbLocation)) {
            $this->dbConnection = new DatabaseConnection($dbLocation);
        }
    }

    public function route()
    {
        switch ($this->request->action) {
            case 'getPool':
                $this->getPool();
                break;

            case 'clearPool':
                $this->clearPool();
                break;

            case 'addSSID':
                $this->addSSID();
                break;

            case 'addSSIDs':
                $this->addSSIDs();
                break;

            case 'removeSSID':
                $this->removeSSID();
                break;

            case 'getPoolLocation':
                $this->getPoolLocation();
                break;

            case 'setPoolLocation':
                $this->setPoolLocation();
                break;

            case 'clearSessionCounter':
                $this->clearSessionCounter();
                break;

            case 'setPineAPSettings':
                $this->setPineAPSettings();
                break;

            case 'getPineAPSettings':
                $this->getPineAPSettings();
                break;

            case 'setEnterpriseSettings':
                $this->setEnterpriseSettings();
                break;

            case 'getEnterpriseSettings':
                $this->getEnterpriseSettings();
                break;

            case 'detectEnterpriseCertificate':
                $this->detectEnterpriseCertificate();
                break;

            case 'generateEnterpriseCertificate':
                $this->generateEnterpriseCertificate();
                break;

            case 'clearEnterpriseCertificate':
                $this->clearEnterpriseCertificate();
                break;

            case 'clearEnterpriseDB':
                $this->clearEnterpriseDB();
                break;

            case 'getEnterpriseData':
                $this->getEnterpriseData();
                break;

            case 'startHandshakeCapture':
                $this->startHandshakeCapture();
                break;

            case 'stopHandshakeCapture':
                $this->stopHandshakeCapture();
                break;

            case 'getHandshake':
                $this->getHandshake();
                break;

            case 'getAllHandshakes':
                $this->getAllHandshakes();
                break;

            case 'checkCaptureStatus':
                $this->checkCaptureStatus();
                break;

            case 'downloadHandshake':
                $this->downloadHandshake();
                break;

            case 'downloadAllHandshakes':
                $this->downloadAllHandshakes();
                break;

            case 'clearAllHandshakes':
                $this->clearAllHandshakes();
                break;

            case 'deleteHandshake':
                $this->deleteHandshake();
                break;

            case 'deauth':
                $this->deauth();
                break;

            case 'enable':
                $this->enable();
                break;

            case 'disable':
                $this->disable();
                break;

            case 'enableAutoStart':
                $this->enableAutoStart();
                break;

            case 'disableAutoStart':
                $this->disableAutoStart();
                break;

            case 'downloadPineAPPool':
                $this->downloadPineAPPool();
                break;

            case 'loadProbes':
                $this->loadProbes();
                break;

            case 'inject':
                $this->inject();
                break;

            case 'countSSIDs':
                $this->countSSIDs();
                break;

            case 'downloadJTRHashes':
                $this->downloadJTRHashes();
                break;

            case 'downloadHashcatHashes':
                $this->downloadHashcatHashes();
                break;
        }
    }

    private function toggleComment($fileName, $lineNumber, $comment)
    {
        $data = file_get_contents($fileName);
        $lines = explode("\n", $data);
        $line = $lines[$lineNumber - 1];
        if (substr($line, 0, 1) === "#") {
            if ($comment) {
                return;
            }
            $line = substr($line, 1);
        } else {
            if (!$comment) {
                return;
            }
            $line = '#' . $line;
        }
        $lines[$lineNumber - 1] = $line;
        file_put_contents($fileName, join("\n", $lines));
    }

    private function isCommented($fileName, $lineNumber)
    {
        $data = file_get_contents($fileName);
        $lines = explode("\n", $data);
        $line = $lines[$lineNumber - 1];
        $ret = substr($line, 0, 1) === "#";
        return $ret;
    }

    private function getDowngradeType()
    {
        if (!$this->isCommented(PineAP::EAP_USER_FILE, 6)) {
            return "MSCHAPV2";
        } else if (!$this->isCommented(PineAP::EAP_USER_FILE, 5)) {
            return "GTC";
        } else {
            return "DISABLE";
        }
    }

    private function enableMSCHAPV2Downgrade()
    {
        $this->toggleComment(PineAP::EAP_USER_FILE, 4, true);
        $this->toggleComment(PineAP::EAP_USER_FILE, 5, true);
        $this->toggleComment(PineAP::EAP_USER_FILE, 6, false);
    }

    private function enableGTCDowngrade()
    {
        $this->toggleComment(PineAP::EAP_USER_FILE, 4, true);
        $this->toggleComment(PineAP::EAP_USER_FILE, 5, false);
        $this->toggleComment(PineAP::EAP_USER_FILE, 6, true);
    }

    private function disableDowngrade()
    {
        $this->toggleComment(PineAP::EAP_USER_FILE, 4, false);
        $this->toggleComment(PineAP::EAP_USER_FILE, 5, true);
        $this->toggleComment(PineAP::EAP_USER_FILE, 6, true);
    }

    private function loadProbes()
    {
        if (!\helper\checkRunningFull("/usr/sbin/pineapd")) {
            $this->response = array('success' => false, 'reason' => "not running");
            return;
        }

        $mac = strtolower($this->request->mac);
        $probesArray = array();

        exec("/usr/bin/pineap list_probes ${mac}", $output);

        foreach ($output as $probeSSID) {
            array_push($probesArray, $probeSSID);
        }

        $this->response = array('success' => true, 'probes' => implode("\n", array_unique($probesArray)));
    }

    private function downloadPineAPPool()
    {
        $poolLocation = '/tmp/ssid_pool.txt';
        $data = $this->getPoolData();
        file_put_contents($poolLocation, $data);
        $this->response = array("download" => $this->downloadFile($poolLocation));
    }

    private function countSSIDs()
    {
        $this->response = array(
            'SSIDs' => substr_count($this->getPoolData(), "\n"),
            'newSSIDs' => substr_count($this->getNewPoolData(), "\n")
        );
    }

    private function enable()
    {
        $this->pineAPHelper->enablePineAP();
        $this->response = array("success" => true);
    }

    private function disable()
    {
        $this->pineAPHelper->disablePineAP();
        $this->response = array("success" => true);
    }

    private function enableAutoStart()
    {
        $this->uciSet("pineap.@config[0].autostart", 1);
        $this->response = array("success" => true);
    }

    private function disableAutoStart()
    {
        $this->uciSet("pineap.@config[0].autostart", 0);
        $this->response = array("success" => true);
    }

    private function checkPineAP()
    {
        if (!$this->checkRunningFull('/usr/sbin/pineapd')) {
            $this->response = array('error' => 'Please start PineAP', 'success' => false);
            return false;
        }
        return true;
    }

    private function deauth()
    {
        if ($this->checkPineAP()) {
            $sta = $this->request->sta;
            $clients = $this->request->clients;
            $multiplier = intval($this->request->multiplier, 10);
            $channel = $this->request->channel;

            if (empty($clients)) {
                $this->response = array('error' => 'This AP has no clients', 'success' => false);
                return;
            }

            foreach ($clients as $client) {
                $mac = $client;
                if (isset($client->mac)) {
                    $mac = $client->mac;
                }
                $success = $this->pineAPHelper->deauth($mac, $sta, $channel, $multiplier);
            }

            if ($success) {
                $this->response = array('success' => true);
            }
        } else {
            $this->response = array('error' => 'Please start PineAP', 'success' => false);
        }
    }

    private function getPoolData()
    {
        $ssidPool = "";
        $rows = $this->dbConnection->query('SELECT * FROM ssids;');
        if (!isset($rows['databaseQueryError'])) {
            foreach ($rows as $row) {
                $ssidPool .= $row['ssid'] . "\n";
            }
        }
        return $ssidPool;
    }

    private function getNewPoolData()
    {
        $ssidPool = "";
        $rows = $this->dbConnection->query('SELECT * FROM ssids WHERE new_ssid=1;');
        if (!isset($rows['databaseQueryError'])) {
            foreach ($rows as $row) {
                $ssidPool .= $row['ssid'] . "\n";
            }
        }
        return $ssidPool;
    }

    private function getPool()
    {
        $this->response = array('ssidPool' => $this->getPoolData(), 'success' => true);
    }

    private function clearPool()
    {
        $this->checkPineAP();
        $this->dbConnection->query('DELETE FROM ssids;');
        $this->response = array('success' => true);
    }

    private function addSSID()
    {
        $this->checkPineAP();
        $ssid = $this->request->ssid;
        $created_date = date('Y-m-d H:i:s');
        if (strlen($ssid) < 1 || strlen($ssid) > 32) {
            $this->error = 'Your SSID must have a length greater than 1 and less than 32.';
        } else {
            @$this->dbConnection->query("INSERT INTO ssids (ssid, created_at) VALUES ('%s', '%s')", $ssid, $created_date);
            $this->response = array('success' => true);
        }
    }

    private function addSSIDs()
    {
        $this->checkPineAP();
        $ssidList = $this->request->ssids;
        $created_date = date('Y-m-d H:i:s');

        foreach ($ssidList as $ssid) {
            if (strlen($ssid) >= 1 && strlen($ssid) <= 32) {
                @$this->dbConnection->query("INSERT INTO ssids (ssid, created_at) VALUES ('%s', '%s');", $ssid, $created_date);
            }
        }
        $this->response = array('success' => true);
    }

    private function removeSSID()
    {
        $this->checkPineAP();
        $ssid = $this->request->ssid;
        if (strlen($ssid) < 1 || strlen($ssid) > 32) {
            $this->error = 'Your SSID must have a length greater than 1 and less than 32.';
        } else {
            $this->dbConnection->query("DELETE FROM ssids WHERE ssid='%s';", $ssid);
            $this->response = array('success' => true);
        }
    }

    private function getPoolLocation()
    {
        $dbBasePath = dirname($this->uciGet("pineap.@config[0].ssid_db_path"));
        $this->response = array('poolLocation' => $dbBasePath . "/");
    }

    private function setPoolLocation()
    {
        $dbLocation = dirname($this->request->location . '/fake_file');
        $this->uciSet("pineap.@config[0].ssid_db_path", $dbLocation . '/pineapple.db');
        $this->response = array('success' => true);
    }

    private function clearSessionCounter()
    {
        $ret = 0;
        $output = array();
        exec('/usr/sbin/resetssids', $output, $ret);
        if ($ret !== 0) {
            $this->error = "Could not clear SSID session counter.";
        } else {
            $this->response = array('success' => true);
        }
    }

    private function getPineAPSettings()
    {
        $sourceMAC = $this->pineAPHelper->getSource();
        $sourceMAC = $sourceMAC === false ? '00:00:00:00:00:00' : $sourceMAC;
        $sourceMAC = strtoupper($sourceMAC);
        $targetMAC = $this->pineAPHelper->getTarget();
        $targetMAC = $targetMAC === false ? 'FF:FF:FF:FF:FF:FF' : $targetMAC;
        $targetMAC = strtoupper($targetMAC);
        $settings = array(
            'allowAssociations' => $this->pineAPHelper->getSetting("karma"),
            'logEvents' => $this->pineAPHelper->getSetting("logging"),
            'pineAPDaemon' => $this->checkRunning("pineapd"),
            'autostartPineAP' => $this->uciGet("pineap.@config[0].autostart"),
            'beaconResponses' => $this->pineAPHelper->getSetting("beacon_responses"),
            'captureSSIDs' => $this->pineAPHelper->getSetting("capture_ssids"),
            'broadcastSSIDs' => $this->pineAPHelper->getSetting("broadcast_ssid_pool"),
            'connectNotifications' => $this->pineAPHelper->getSetting("connect_notifications"),
            'disconnectNotifications' => $this->pineAPHelper->getSetting("disconnect_notifications"),
            'broadcastInterval' => $this->pineAPHelper->getSetting("beacon_interval"),
            'responseInterval' => $this->pineAPHelper->getSetting("beacon_response_interval"),
            'sourceMAC' => $sourceMAC,
            'targetMAC' => $targetMAC
        );
        $this->response = array('settings' => $settings, 'success' => true);
        return $settings;
    }

    private function setPineAPSettings()
    {
        $settings = $this->request->settings;
        if ($settings->allowAssociations) {
            $this->pineAPHelper->enableAssociations();
            $this->uciSet("pineap.@config[0].karma", 'on');
        } else {
            $this->pineAPHelper->disableAssociations();
            $this->uciSet("pineap.@config[0].karma", 'off');
        }
        if ($settings->logEvents) {
            $this->pineAPHelper->enableLogging();
            $this->uciSet("pineap.@config[0].logging", 'on');
        } else {
            $this->pineAPHelper->disableLogging();
            $this->uciSet("pineap.@config[0].logging", 'off');
        }
        if ($settings->beaconResponses) {
            $this->pineAPHelper->enableResponder();
            $this->uciSet("pineap.@config[0].beacon_responses", 'on');
        } else {
            $this->pineAPHelper->disableResponder();
            $this->uciSet("pineap.@config[0].beacon_responses", 'off');
        }
        if ($settings->captureSSIDs) {
            $this->pineAPHelper->enableHarvester();
            $this->uciSet("pineap.@config[0].capture_ssids", 'on');
        } else {
            $this->pineAPHelper->disableHarvester();
            $this->uciSet("pineap.@config[0].capture_ssids", 'off');
        }
        if ($settings->broadcastSSIDs) {
            $this->pineAPHelper->enableBeaconer();
            $this->uciSet("pineap.@config[0].broadcast_ssid_pool", 'on');
        } else {
            $this->pineAPHelper->disableBeaconer();
            $this->uciSet("pineap.@config[0].broadcast_ssid_pool", 'off');
        }
        if ($settings->connectNotifications) {
            $this->pineAPHelper->enableConnectNotifications();
            $this->uciSet("pineap.@config[0].connect_notifications", 'on');
        } else {
            $this->pineAPHelper->disableConnectNotifications();
            $this->uciSet("pineap.@config[0].connect_notifications", 'off');
        }
        if ($settings->disconnectNotifications) {
            $this->pineAPHelper->enableDisconnectNotifications();
            $this->uciSet("pineap.@config[0].disconnect_notifications", 'on');
        } else {
            $this->pineAPHelper->disableDisconnectNotifications();
            $this->uciSet("pineap.@config[0].disconnect_notifications", 'off');
        }
        $this->pineAPHelper->setBeaconInterval($settings->broadcastInterval);
        $this->uciSet("pineap.@config[0].beacon_interval", $settings->broadcastInterval);
        $this->pineAPHelper->setResponseInterval($settings->responseInterval);
        $this->uciSet("pineap.@config[0].beacon_response_interval", $settings->responseInterval);
        $this->pineAPHelper->setTarget($settings->targetMAC);
        $this->uciSet("pineap.@config[0].target_mac", $settings->targetMAC);
        $this->pineAPHelper->setSource($settings->sourceMAC);
        $this->uciSet("pineap.@config[0].pineap_mac", $settings->sourceMAC);

        $this->response = array("success" => true);
    }


    private function detectEnterpriseCertificate()
    {
        if (file_exists('/etc/pineape/certs/server.crt')) {
            $this->response = array("installed" => true);
        } else {
            $this->response = array("installed" => false);
        }
    }

    private function generateEnterpriseCertificate()
    {
        $params = $this->request->certSettings;

        $state = $params->state;
        $country = $params->country;
        $locality = $params->locality;
        $organization = $params->organization;
        $email = $params->email;
        $commonname = $params->commonname;

        if ((strlen($state) < 1 || strlen($state) > 32) ||
            (strlen($country) < 2 || strlen($country) > 2) ||
            (strlen($locality) < 1 || strlen($locality) > 32) ||
            (strlen($organization) < 1 || strlen($organization) > 32) ||
            (strlen($email) < 1 || strlen($email) > 32) ||
            (strlen($commonname) < 1 || strlen($commonname) > 32)) {
            $this->error = "Invalid settings provided.";
            return;
        }

        $state = escapeshellarg($params->state);
        $country = escapeshellarg($params->country);
        $locality = escapeshellarg($params->locality);
        $organization = escapeshellarg($params->organization);
        $email = escapeshellarg($params->email);
        $commonname = escapeshellarg($params->commonname);

        exec("cd /etc/pineape/certs && ./clean.sh");
        exec("/etc/pineape/certs/configure.sh -p pineapplesareyummy -c ${country} -s ${state} -l ${locality} -o ${organization} -e ${email} -n ${commonname}");
        $this->execBackground("/etc/pineape/certs/bootstrap.sh");

        $this->response = array("success" => true);
    }

    private function clearEnterpriseCertificate()
    {
        exec("cd /etc/pineape/certs && ./clean.sh");
        $this->uciSet("wireless.@wifi-iface[2].disabled", "1");
        $this->execBackground("wifi down radio0 && wifi up radio0");
        $this->response = array("success" => true);
    }

    private function clearEnterpriseDB()
    {
        $dbLocation = "/etc/pineapple/pineape.db";
        $this->dbConnection = new DatabaseConnection($dbLocation);

        $this->dbConnection->exec("DELETE FROM chalresp; DELETE FROM basic;");
        $this->response = array("success" => true);
    }

    private function getEnterpriseSettings()
    {
        $settings = array(
            'enabled' => $this->getEnterpriseRunning(),
            'enableAssociations' => $this->getEnterpriseAllowAssocs(),
            'ssid' => $this->uciGet('wireless.@wifi-iface[2].ssid'),
            'mac' => $this->uciGet('wireless.@wifi-iface[2].macaddr'),
            'encryptionType' => $this->uciGet('wireless.@wifi-iface[2].encryption'),
            'downgrade' => $this->getDowngradeType(),
        );

        $this->response = array("settings" => $settings);
    }

    private function setEnterpriseSettings()
    {
        $settings = $this->request->settings;
        if ((strlen($settings->ssid) < 1 || strlen($settings->ssid) > 32) ||
            (strlen($settings->mac) < 17 || strlen($settings->mac) > 17)) {
            $this->error = "Invalid settings provided.";
            return;
        }
        $this->uciSet("wireless.@wifi-iface[2].ssid", $settings->ssid);
        $this->uciSet("wireless.@wifi-iface[2].macaddr", $settings->mac);
        $this->uciSet("wireless.@wifi-iface[2].encryption", $settings->encryptionType);
        if ($settings->enabled) {
            $this->uciSet("wireless.@wifi-iface[2].disabled", "0");
        } else {
            $this->uciSet("wireless.@wifi-iface[2].disabled", "1");
        }

        if ($settings->enableAssociations) {
            $this->uciSet("pineap.@config[0].pineape_passthrough", "on");
        } else {
            $this->uciSet("pineap.@config[0].pineape_passthrough", "off");
        }

        switch (strtoupper($settings->downgrade)) {
            case "MSCHAPV2":
                $this->enableMSCHAPV2Downgrade();
                break;
            case "GTC":
                $this->enableGTCDowngrade();
                break;
            case "DISABLE":
            default:
                $this->disableDowngrade();
        }

        $this->execBackground("wifi down radio0 && wifi up radio0");
        $this->response = array("success" => true);
    }

    private function getEnterpriseData()
    {
        $dbLocation = "/etc/pineapple/pineape.db";
        $this->dbConnection = new DatabaseConnection($dbLocation);

        $chalrespdata = array();
        $rows = $this->dbConnection->query("SELECT type, username, hex(challenge), hex(response) FROM chalresp;");
        foreach ($rows as $row) {
            $x = array();
            $x['type'] = $row['type'];
            $x['username'] = $row['username'];
            $x['challenge'] = $row['hex(challenge)'];
            $x['response'] = $row['hex(response)'];
            array_push($chalrespdata, $x);
        }

        $basicdata = array();
        $rows = $this->dbConnection->query("SELECT type, identity, password FROM basic;");
        foreach ($rows as $row) {
            $x = array();
            $x['type'] = $row['type'];
            $x['username'] = $row['identity'];
            $x['password'] = $row['password'];
            array_push($basicdata, $x);
        }
        $this->response = array("success" => true, "chalrespdata" => $chalrespdata, "basicdata" => $basicdata);
    }

    private function downloadJTRHashes()
    {
        $jtrLocation = '/tmp/enterprise_jtr.txt';
        $dbLocation = "/etc/pineapple/pineape.db";
        $this->dbConnection = new DatabaseConnection($dbLocation);
        $data = array();
        $rows = $this->dbConnection->query("SELECT type, username, hex(challenge), hex(response) FROM chalresp;");
        foreach ($rows as $row) {
            if (strtoupper($row['type']) !== "MSCHAPV2" && strtoupper($row['type']) != "EAP-TTLS/MSCHAPV2") {
                continue;
            }
            $x = $row['username'] . ':$NETNTLM$' . $row['hex(challenge)'] . '$' . $row['hex(response)'];
            array_push($data, $x);
        }
        file_put_contents($jtrLocation, join("\n", $data));
        $this->response = array("download" => $this->downloadFile($jtrLocation));
    }

    private function downloadHashcatHashes()
    {
        $hashcatLocation = '/tmp/enterprise_hashcat.txt';
        $dbLocation = "/etc/pineapple/pineape.db";
        $this->dbConnection = new DatabaseConnection($dbLocation);
        $data = array();
        $rows = $this->dbConnection->query("SELECT type, username, hex(challenge), hex(response) FROM chalresp;");
        foreach ($rows as $row) {
            if (strtoupper($row['type']) !== "MSCHAPV2" && strtoupper($row['type']) != "EAP-TTLS/MSCHAPV2") {
                continue;
            }
            $x = $row['username'] . '::::' . $row['hex(response)'] . ':' . $row['hex(challenge)'];
            array_push($data, $x);
        }
        file_put_contents($hashcatLocation, join("\n", $data));
        $this->response = array("download" => $this->downloadFile($hashcatLocation));
    }

    private function getEnterpriseRunning()
    {
        exec("hostapd_cli -i wlan0-2 pineape_enable_status", $statusOutput);
        if ($statusOutput[0] == "ENABLED") {
            return true;
        }

        return false;
    }

    private function getEnterpriseAllowAssocs()
    {
        exec("hostapd_cli -i wlan0-2 pineape_auth_passthrough_status", $statusOutput);
        if ($statusOutput[0] == "ENABLED") {
            return true;
        }
        return false;
    }

    private function startHandshakeCapture()
    {
        $bssid = $this->request->bssid;
        $channel = $this->request->channel;
        if ($this->checkPineAP()) {
            $this->execBackground("pineap /etc/pineap.conf handshake_capture_start ${bssid} ${channel}");
            $this->response = array('success' => true);
            return;
        } else {
            // We already set $this->response in checkPineAP() if it isnt running.
            return;
        }
    }

    private function stopHandshakeCapture()
    {
        $this->execBackground('pineap /tmp/pineap.conf handshake_capture_stop');
        $this->response = array('success' => true);
    }

    private function getHandshake()
    {
        $bssid = str_replace(':', '-', $this->request->bssid);
        if (file_exists("/tmp/handshakes/{$bssid}_full.pcap")) {
            $this->response = array('handshakeExists' => true, 'partial' => false);
        } else if (file_exists("/tmp/handshakes/{$bssid}_partial.pcap")) {
            $this->response = array('handshakeExists' => true, 'partial' => true);
        } else {
            $this->response = array('handshakeExists' => false);
        }
    }

    private function getAllHandshakes()
    {
        $handshakes = array();
        foreach (glob("/tmp/handshakes/*.pcap") as $handshake) {
            $handshake = str_replace("/tmp/handshakes/", "", $handshake);
            $handshake = str_replace("_full.pcap", "", $handshake);
            $handshake = str_replace("_partial.pcap", "", $handshake);
            $handshake = str_replace('-', ':', $handshake);
            array_push($handshakes, $handshake);
        }

        $this->response = array("handshakes" => $handshakes);
    }

    private function downloadAllHandshakes()
    {
        @unlink('/tmp/handshakes/handshakes.tar.gz');
        exec("tar -czf /tmp/handshakes/handshakes.tar.gz -C /tmp/handshakes .");
        $this->response = array("download" => $this->downloadFile("/tmp/handshakes/handshakes.tar.gz"));
    }

    private function clearAllHandshakes()
    {
        @unlink("/tmp/handshakes/handshakes.tar.gz");
        foreach (glob("/tmp/handshakes/*.pcap") as $handshake) {
            unlink($handshake);
        }

        $this->response = array("success" => true);
    }

    private function checkCaptureStatus()
    {
        $bssid = $this->request->bssid;
        exec("pineap /tmp/pineap.conf get_status", $status_output);
        if ($status_output[0] === "PineAP is not running") {
            $this->error = "PineAP is not running";
            return 0;
        } else {
            $status_output = implode("\n", $status_output);
            $status_output = json_decode($status_output, true);
            if ($status_output['captureRunning'] === true && $status_output['bssid'] === $bssid) {
                // A scan is running for the supplied BSSID.
                $this->response = array('running' => true, 'currentBSSID' => true, 'bssid' => $status_output['bssid']);
                return 3;
            } elseif ($status_output['captureRunning'] === true) {
                // A scan is running, but not for this BSSID.
                $this->response = array('running' => true, 'currentBSSID' => false, 'bssid' => $status_output['bssid']);
                return 2;
            } else {
                // No scan is running.
                $this->response = array('running' => false, 'currentBSSID' => false);
                return 0;
            }
        }
    }

    private function downloadHandshake()
    {
        $bssid = str_replace(':', '-', $this->request->bssid);
        $type = $this->request->type;
        // JTR and hashcat don't care whether the data came from a full or partial handshake
        if ($type === "pcap") {
            $suffix = "_";
            if (file_exists("/tmp/handshakes/{$bssid}_full.pcap")) {
                $suffix .= "full";
            } else {
                $suffix .= "partial";
            }
            $this->response = array("download" => $this->downloadFile("/tmp/handshakes/{$bssid}{$suffix}.pcap"));
        } else {
            $this->response = array("download" => $this->downloadFile("/tmp/handshakes/{$bssid}.{$type}"));
        }
    }

    private function deleteHandshake()
    {
        $bssid = str_replace(':', '-', $this->request->bssid);
        @unlink("/tmp/handshakes/${bssid}_full.pcap");
        @unlink("/tmp/handshakes/${bssid}_partial.pcap");
        @unlink("/tmp/handshakes/${bssid}.hccap");
        @unlink("/tmp/handshakes/${bssid}.txt");

        $this->response = array('success' => true);
    }

    private function inject()
    {
        $payload = preg_replace('/[^A-Fa-f0-9]/', '', $this->request->payload);
        if (hex2bin($payload) === false) {
            $this->error = 'Invalid hex';
            return;
        }
        if (!$this->checkRunningFull('/usr/sbin/pineapd')) {
            $this->error = 'Please start PineAP';
            return;
        }
        file_put_contents('/tmp/inject', $payload);
        $channel = intval($this->request->channel);
        $frameCount = intval($this->request->frameCount);
        $delay = intval($this->request->delay);
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );
        $cmd = "/usr/bin/pineap /tmp/pineap.conf inject /tmp/inject ${channel} ${frameCount} ${delay}";
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            $this->response = array('error' => "Failed to spawn process for command: ${cmd}", 'command' => $cmd);
            unlink('/tmp/inject');
            return;
        }
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        $exitCode = proc_close($process);
        if (empty($output)) {
            $this->response = array(
                'success' => true,
                'request' => $this->request,
                'payload' => json_encode($payload),
                'command' => $cmd
            );
        } else {
            $this->response = array(
                'error' => 'PineAP cli did not execute successfully',
                'command' => $cmd,
                'exitCode' => $exitCode,
                'stdout' => $output,
                'stderr' => $errorOutput
            );
        }
        unlink('/tmp/inject');
    }
}
