<?php namespace pineapple;

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

class Recon extends SystemModule
{
    private $scanID = null;
    private $dbConnection = null;

    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = false;

        $dbLocation = $this->uciGet("pineap.@config[0].recon_db_path");
        if (file_exists($dbLocation)) {
            $this->dbConnection = new DatabaseConnection($dbLocation);
        }
    }

    public function route()
    {
        switch ($this->request->action) {
            case 'startPineAPDaemon':
                $this->startPineAPDaemon();
                break;

            case 'checkPineAPDaemon':
                $this->checkPineAPDaemon();
                break;

            case 'startNormalScan':
                $this->startNormalScan();
                break;

            case 'startLiveScan':
                $this->startLiveScan();
                break;

            case 'startReconPP':
                $this->startReconPP();
                break;

            case 'stopScan':
                $this->stopScan();
                break;

            case 'getScans':
                $this->getScans();
                break;

            case 'getScanLocation':
                $this->getScanLocation();
                break;

            case 'setScanLocation':
                $this->setScanLocation();
                break;

            case 'checkScanStatus':
                $this->checkScanStatus();
                break;

            case 'loadResults':
                $this->loadResults($this->request->scanID);
                break;

            case 'downloadResults':
                $this->downloadResults();
                break;

            case 'removeScan':
                $this->removeScan();
                break;

            case 'getWSAuthToken':
                $this->getWSAuthToken();
                break;
        }
    }

    private function startPineAPDaemon()
    {
        if(!$this->checkRunningFull("/usr/sbin/pineapd")) {
            exec("/etc/init.d/pineapd start");
        }
        $this->response = array("success" => true);
    }

    private function checkPineAPDaemon()
    {
        if($this->checkRunningFull("/usr/sbin/pineapd")) {
           return true;
        } else {
            return false;
        }
    }

    private function startNormalScan()
    {
        $scanDuration = $this->request->scanDuration;
        $scanType = $this->request->scanType;
        if ($this->checkPineAPDaemon()) {
            $this->startPineAPDaemon();
            exec("pineap /tmp/pineap.conf run_scan {$scanDuration} {$scanType}");
            $scanID = $this->getCurrentScanID();
            $this->response = array("success" => true, "scanID" => $scanID);
        } else {
            $this->error = "The PineAP Daemon must be running.";
        }
    }

    private function startReconPP()
    {

        if ($this->checkRunningFull("python /pineapple/modules/Recon/api/reconpp.py")) {
           $this->response = array("success" => true);
           return;
        }

        $dbPath = $this->uciGet("pineap.@config[0].recon_db_path");
        $scanID = $this->getCurrentScanID();
        $this->execBackground("python /pineapple/modules/Recon/api/reconpp.py {$dbPath} {$scanID}");

        $this->response = array("success" => true);
    }

    private function startLiveScan()
    {
        $scanDuration = $this->request->scanDuration;
        $scanType = $this->request->scanType;
        $scanID = 0;
        $dbLocation = $this->uciGet("pineap.@config[0].recon_db_path");

        if ($this->checkPineAPDaemon()) {
            // Check if a scan is already in progress
            if (!is_numeric($this->getCurrentScanID())) {
                exec("pineap /tmp/pineap.conf run_scan {$scanDuration} {$scanType}");
                $scanID = $this->getCurrentScanID();
                $this->execBackground("python /pineapple/modules/Recon/api/reconpp.py {$dbLocation} {$scanID}");
            }
            $this->startReconPP();
            $this->response = array("success" => true, "scanID" => $scanID);
        } else {
            $this->error = "The PineAP Daemon must be running.";
        }
    }

    private function stopScan()
    {
        $this->execBackground("pineap /tmp/pineap.conf stop_scan");
        $this->execBackground("pkill -9 -f /pineapple/modules/Recon/api/reconpp.py");
        if (file_exists('/tmp/reconpp.lock')) {
            unlink('/tmp/reconpp.lock');
        }
        $this->response = array("success" => true);
    }

    private function getCurrentScanID()
    {
        exec("pineap /tmp/pineap.conf get_status", $status_output);
        if ($status_output[0] === "PineAP is not running") {
            $this->stopScan();
            $this->response = array("completed" => true, "error" => "The PineAP Daemon must be running.");
            return null;
        }

        $status_output = implode("\n", $status_output);
        $status_output = json_decode($status_output, true);
        $scanID = $status_output['scanID'];

        return $scanID;
    }

    private function wsRunning()
    {
        return $this->checkRunningFull('python /pineapple/modules/Recon/api/reconpp.py');
    }

    private function checkScanStatus()
    {
        exec("pineap /tmp/pineap.conf get_status", $status_output);
        if ($status_output[0] === "PineAP is not running") {
            $this->stopScan();
            $this->response = array("completed" => true, "error" => "The PineAP Daemon must be running.");
            return;
        }

        $status_output = implode("\n", $status_output);
        $status_output = json_decode($status_output, true);

        if ($status_output['scanRunning'] === false) {
            $this->stopScan();
            $this->response = array("completed" => true);
        } else if ($status_output['scanRunning'] === true) {
            if ($status_output['captureRunning'] === true) {
                $resp = array("completed" => false,
                    "scanID" => $status_output['scanID'],
                    "scanPercent" => $status_output['scanPercent'],
                    "continuous" => $status_output['continuous'],
                    "live" => $this->wsRunning(),
                    "captureRunning" => true);
            } else {
                $resp = array("completed" => false,
                    "scanID" => $status_output['scanID'],
                    "scanPercent" => $status_output['scanPercent'],
                    "continuous" => $status_output['continuous'],
                    "live" => $this->wsRunning(),
                    "captureRunning" => false);
            }

            $this->response = $resp;
        } else {
            $this->stopScan();
            $this->response = array("completed" => true, "debug" => $status_output);
        }
    }

    private function loadResults($scanID)
    {
        $accessPoints = array();
        $unassociatedClients = array();
        $outOfRangeClients = array();

        $rows = $this->dbConnection->query("SELECT * FROM aps WHERE scan_id = '%s';", $scanID);
        foreach ($rows as $row) {
            $ap = array();
            $ap['ssid'] = $row['ssid'];
            $ap['bssid'] = $row['bssid'];
            $ap['encryption'] = $this->printEncryption($row['encryption']);
            $ap['channel'] = $row['channel'];
            $ap['power'] = $row['signal'];
            $ap['lastSeen'] = $row['last_seen'];
            $ap['wps'] = $row['wps'];
            $ap['clients'] = array();
            $accessPoints[$row['bssid']] = $ap;
        }

        $rows = $this->dbConnection->query("SELECT * FROM clients WHERE scan_id = '%s';", $scanID);
        foreach ($rows as $row) {
            $bssid = $row['bssid'];
            $mac   = $row['mac'];
            $lastSeen = $row['last_seen'];
            $ap   = $accessPoints[$bssid];
            if ($bssid == "FF:FF:FF:FF:FF:FF") {
                array_push($unassociatedClients, array('mac' => $mac, 'lastSeen' => $lastSeen));
            } else if ($ap != null && in_array($bssid, $ap)) {
                array_push($accessPoints[$bssid]['clients'], array('mac' => $mac, 'lastSeen' => $lastSeen));
            } else {
                $outOfRangeClients[$mac] = array('bssid' => $bssid, 'lastSeen' => $lastSeen);
            }
        }

        $realAPs = array();
        foreach ($accessPoints as $key => $value) {
            array_push($realAPs, $value);
        }

        $returnArray['ap_list'] = $realAPs;
        $returnArray['unassociated_clients'] = $unassociatedClients;
        $returnArray['out_of_range_clients'] = $outOfRangeClients;

        $this->response = array("results" => $returnArray);
        return $returnArray;
    }

    private function printEncryption($encryptionType)
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
        // Fix the code below - these never trigger. Make sure to set "return WEP" to retStr += WEP
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

    private function utcToPineapple($timeStr) {
        $d = new \DateTime($timeStr . ' UTC');
        exec("date +%Z", $tz);
        $tz = $tz[0];
        $tzo = new \DateTimeZone($tz);
        $d->setTimezone($tzo);
        return $d->format('Y-m-d G:i:s T');
    }

    private function getScanObject($scanID)
    {
        $data = array();
        $data[$scanID] = array();
        $aps = $this->dbConnection->query("SELECT scan_id, ssid, bssid, encryption, hidden, channel, signal, wps, last_seen FROM aps WHERE scan_id='%d';", $scanID);
        foreach ($aps as $ap_row) {
            $data[$scanID]['aps'][$ap_row['bssid']] = array();
            $data[$scanID]['aps'][$ap_row['bssid']]['ssid'] = $ap_row['ssid'];
            $data[$scanID]['aps'][$ap_row['bssid']]['encryption'] = $this->printEncryption($ap_row['encryption']);
            $data[$scanID]['aps'][$ap_row['bssid']]['hidden'] = $ap_row['hidden'];
            $data[$scanID]['aps'][$ap_row['bssid']]['channel'] = $ap_row['channel'];
            $data[$scanID]['aps'][$ap_row['bssid']]['signal'] = $ap_row['signal'];
            $data[$scanID]['aps'][$ap_row['bssid']]['wps'] = $ap_row['wps'];
            $data[$scanID]['aps'][$ap_row['bssid']]['last_seen'] = $ap_row['last_seen'];
            $data[$scanID]['aps'][$ap_row['bssid']]['clients'] = array();
            $clients = $this->dbConnection->query("SELECT scan_id, mac, bssid, last_seen FROM clients WHERE scan_id='%d' AND bssid='%s';", $ap_row['scan_id'], $ap_row['bssid']);
            foreach ($clients as $client_row) {
                $data[$scanID]['aps'][$ap_row['bssid']]['clients'][$client_row['mac']] = array();
                $data[$scanID]['aps'][$ap_row['bssid']]['clients'][$client_row['mac']]['bssid'] = $client_row['bssid'];
                $data[$scanID]['aps'][$ap_row['bssid']]['clients'][$client_row['mac']]['last_seen'] = $client_row['last_seen'];
            }
        }
        $data[$scanID]['outOfRangeClients'] = array();
        $clients = $this->dbConnection->query("
            SELECT t1.mac, t1.bssid, t1.last_seen FROM clients t1
            LEFT JOIN aps t2 ON
            t2.bssid = t1.bssid WHERE t2.bssid IS NULL AND
            t1.bssid != 'FF:FF:FF:FF:FF:FF' COLLATE NOCASE AND t1.scan_id='%d';
            ", $ap_row['scan_id']);
        foreach ($clients as $client_row) {
            $data[$scanID]['outOfRangeClients'][$client_row['mac']] = array();
            $data[$scanID]['outOfRangeClients'][$client_row['mac']] = $client_row['bssid'];
        }
        $data[$scanID]['unassociatedClients'] = array();
        $clients = $this->dbConnection->query("SELECT mac FROM clients WHERE bssid='FF:FF:FF:FF:FF:FF' COLLATE NOCASE;");
        foreach ($clients as $client_row) {
            array_push($data[$scanID]['unassociatedClients'], $client_row['mac']);
        }
        return $data;
    }

    private function downloadResults()
    {
        $fileData = $this->getScanObject($this->request->scanID);
        $fileName = '/tmp/recon_data.json';
        file_put_contents($fileName, json_encode($fileData, JSON_PRETTY_PRINT));
        $this->response = array("download" => $this->downloadFile($fileName));
    }

    private function getScans()
    {
        if ($this->dbConnection) {
            $scans = $this->dbConnection->query("SELECT * FROM scan_ids ORDER BY date DESC;");
            if (!isset($scans['databaseQueryError'])) {
                $this->response = array('scans' => $scans);
                return;
            }
        }
        $this->response = array('scans' => array());
    }

    private function getScanLocation()
    {
        $scanLocation = $dbBasePath = dirname($this->uciGet("pineap.@config[0].recon_db_path"));
        $this->response = array("success" => true, "scanLocation" => $scanLocation . "/");
    }

    private function setScanLocation()
    {
        $scanLocation = $this->request->scanLocation;
        if (!empty($scanLocation)) {
            $dbLocation = dirname($this->request->scanLocation . '/fake_file');
            $this->uciSet("pineap.@config[0].recon_db_path", $dbLocation . '/recon.db');
            if ($this->checkPineAPDaemon()) {
                exec("/etc/init.d/pineapd stop");
                $this->startPineAPDaemon();
            }
            $this->response = array("success" => true);
        } else {
            $this->error = "You cannot specify an empty path.";
        }
    }

    private function removeScan()
    {
        $this->dbConnection->exec("DELETE FROM clients WHERE scan_id='%s';", $this->request->scanID);
        $this->dbConnection->exec("DELETE FROM aps WHERE scan_id='%s';", $this->request->scanID);
        $this->dbConnection->exec("DELETE FROM scan_ids WHERE scan_id='%s';", $this->request->scanID);

        $this->response = array("success" => true);
    }

    private function getWSAuthToken()
    {
        @$wsAuthToken = file_get_contents('/tmp/reconpp.token');
        if ($wsAuthToken === false) {
            $this->response = array("success" => false);
        } else {
            $this->response = array("success" => true, "wsAuthToken" => $wsAuthToken);
        }
    }
}