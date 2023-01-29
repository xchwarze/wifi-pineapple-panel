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
    private $dbConnection = null;
    const PATH_WS_SCRIPT = '/pineapple/modules/Recon/api/reconpp.py';
    const CLI_PINEAP = 'pineap /tmp/pineap.conf';

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
        if ($this->checkPineAPDaemon()) {
            $this->response = ["success" => true];
            return;
        }

        exec("/etc/init.d/pineapd start", $status_output);
        if ($status_output[0] === "Status: OK") {
            $this->response = ["success" => true];
        } else {
            $this->response = ["message" => implode("\n", $status_output)];
        }
    }

    private function checkPineAPDaemon()
    {
        return (bool) $this->checkRunningFull("/usr/sbin/pineapd");
    }

    private function startNormalScan()
    {
        $scanDuration = $this->request->scanDuration;
        $scanType = $this->request->scanType;
        if ($this->checkPineAPDaemon()) {
            $this->startPineAPDaemon();
            exec(Recon::CLI_PINEAP . " run_scan {$scanDuration} {$scanType}");
            $scanID = $this->getCurrentScanID();
            $this->response = ["success" => true, "scanID" => $scanID];
        } else {
            $this->error = "The PineAP Daemon must be running.";
        }
    }

    private function startReconPP()
    {
        if ($this->checkRunningFull("python " . Recon::PATH_WS_SCRIPT)) {
           $this->response = ["success" => true];
           return;
        }

        $dbPath = $this->uciGet("pineap.@config[0].recon_db_path");
        $scanID = $this->getCurrentScanID();
        $this->execBackground("python " . Recon::PATH_WS_SCRIPT . " {$dbPath} {$scanID}");

        $this->response = ["success" => true];
    }

    private function startLiveScan()
    {
        if ($this->checkPineAPDaemon()) {
            $scanDuration = $this->request->scanDuration;
            $scanType = $this->request->scanType;
            $scanID = 0;
            $dbLocation = $this->uciGet("pineap.@config[0].recon_db_path");

            // Check if a scan is already in progress
            if (!is_numeric($this->getCurrentScanID())) {
                exec(Recon::CLI_PINEAP . " run_scan {$scanDuration} {$scanType}");
                $scanID = $this->getCurrentScanID();
                $this->execBackground("python " . Recon::PATH_WS_SCRIPT . " {$dbLocation} {$scanID}");
            }
            $this->startReconPP();
            $this->response = ["success" => true, "scanID" => $scanID];
        } else {
            $this->error = "The PineAP Daemon must be running.";
        }
    }

    private function stopScan()
    {
        $this->execBackground(Recon::CLI_PINEAP . " stop_scan");
        $this->execBackground("pkill -9 -f " . Recon::PATH_WS_SCRIPT);
        if (file_exists('/tmp/reconpp.lock')) {
            unlink('/tmp/reconpp.lock');
        }
        $this->response = ["success" => true];
    }

    private function getCurrentScanID()
    {
        exec(Recon::CLI_PINEAP . " get_status", $status_output);
        if ($status_output[0] === "PineAP is not running") {
            $this->stopScan();
            $this->response = ["completed" => true, "error" => "The PineAP Daemon must be running."];
            return null;
        }

        $status_output = json_decode(implode("\n", $status_output), true);

        return $status_output['scanID'];
    }

    private function wsRunning()
    {
        return $this->checkRunningFull("python " . Recon::PATH_WS_SCRIPT);
    }

    private function checkScanStatus()
    {
        exec(Recon::CLI_PINEAP . " get_status", $status_output);
        if ($status_output[0] === "PineAP is not running") {
            $this->stopScan();
            $this->response = ["completed" => true, "error" => "The PineAP Daemon must be running."];
            return;
        }

        $status_output = json_decode(implode("\n", $status_output), true);
        if ($status_output['scanRunning'] === false) {
            $this->stopScan();
            $this->response = ["completed" => true];
        } else if ($status_output['scanRunning'] === true) {
            $this->response = [
                "completed" => false,
                "scanID" => $status_output['scanID'],
                "scanPercent" => $status_output['scanPercent'],
                "continuous" => $status_output['continuous'],
                "live" => $this->wsRunning(),
                "captureRunning" => $status_output['captureRunning'] === true
            ];
        } else {
            $this->stopScan();
            $this->response = ["completed" => true, "debug" => $status_output];
        }
    }

    private function loadResults($scanID)
    {
        $accessPoints = [];
        $unassociatedClients = [];
        $outOfRangeClients = [];

        $rows = $this->dbConnection->query("SELECT ssid, bssid, encryption, channel, signal, last_seen, wps FROM aps WHERE scan_id = '%s';", $scanID);
        foreach ($rows as $row) {
            $accessPoints[ $row['bssid'] ] = [
                'ssid' => $row['ssid'],
                'bssid' => $row['bssid'],
                'encryption' => $this->printEncryption($row['encryption']),
                'channel' => $row['channel'],
                'power' => $row['signal'],
                'lastSeen' => $row['last_seen'],
                'wps' => $row['wps'],
                'clients' => []
            ];
        }

        $rows = $this->dbConnection->query("SELECT bssid, mac, last_seen FROM clients WHERE scan_id = '%s';", $scanID);
        foreach ($rows as $row) {
            $bssid = $row['bssid'];
            $mac   = $row['mac'];
            $lastSeen = $row['last_seen'];

            if ($bssid == "FF:FF:FF:FF:FF:FF") {
                $unassociatedClients[] = ['mac' => $mac, 'lastSeen' => $lastSeen];
            } else if ($accessPoints[ $bssid ] != null && in_array($bssid, $accessPoints[ $bssid ])) {
                $accessPoints[$bssid]['clients'][] = ['mac' => $mac, 'lastSeen' => $lastSeen];
            } else {
                $outOfRangeClients[$mac] = ['bssid' => $bssid, 'lastSeen' => $lastSeen];
            }
        }

        $realAPs = [];
        foreach ($accessPoints as $key => $value) {
            $realAPs[] = $value;
        }

        $returnArray = [
            'ap_list' => $realAPs,
            'unassociated_clients' => $unassociatedClients,
            'out_of_range_clients' => $outOfRangeClients,
        ];

        $this->response = ["results" => $returnArray];
        return $returnArray;
    }

    private function printEncryption($encryptionType)
    {
        if ($encryptionType === 0) {
            return 'Open';
        }

        $retStr = '';
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

        return substr($retStr, 0, -1) . ')';
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
        $data = [
            $scanID => [
                'outOfRangeClients' => [],
                'unassociatedClients' => []
            ]
        ];

        $aps = $this->dbConnection->query("SELECT scan_id, ssid, bssid, encryption, hidden, channel, signal, wps, last_seen FROM aps WHERE scan_id='%d';", $scanID);
        foreach ($aps as $ap_row) {
            $data[ $scanID ]['aps'][ $ap_row['bssid'] ] = [
                'ssid' => $ap_row['ssid'],
                'encryption' => $this->printEncryption($ap_row['encryption']),
                'hidden' => $ap_row['hidden'],
                'channel' => $ap_row['channel'],
                'signal' => $ap_row['signal'],
                'wps' => $ap_row['wps'],
                'last_seen' => $ap_row['last_seen'],
                'clients' => []
            ];

            $clients = $this->dbConnection->query("SELECT scan_id, mac, bssid, last_seen FROM clients WHERE scan_id='%d' AND bssid='%s';", $ap_row['scan_id'], $ap_row['bssid']);
            foreach ($clients as $client_row) {
                $data[ $scanID ]['aps'][ $ap_row['bssid'] ]['clients'][ $client_row['mac'] ] = [
                    'bssid' => $client_row['bssid'],
                    'last_seen' => $client_row['last_seen']
                ];
            }
        }

        $clients = $this->dbConnection->query("
            SELECT t1.mac, t1.bssid, t1.last_seen FROM clients t1
            LEFT JOIN aps t2 ON
            t2.bssid = t1.bssid WHERE t2.bssid IS NULL AND
            t1.bssid != 'FF:FF:FF:FF:FF:FF' COLLATE NOCASE AND t1.scan_id='%d';
            ", $scanID);
        foreach ($clients as $client_row) {
            $data[ $scanID ]['outOfRangeClients'][ $client_row['mac'] ] = [
                $client_row['bssid']
            ];
        }

        $clients = $this->dbConnection->query("SELECT mac FROM clients WHERE bssid='FF:FF:FF:FF:FF:FF' COLLATE NOCASE;");
        foreach ($clients as $client_row) {
            $data[$scanID]['unassociatedClients'][] = $client_row['mac'];
        }

        return $data;
    }

    private function downloadResults()
    {
        $fileData = $this->getScanObject($this->request->scanID);
        $fileName = '/tmp/recon_data.json';
        file_put_contents($fileName, json_encode($fileData, JSON_PRETTY_PRINT));
        $this->response = ["download" => $this->downloadFile($fileName)];
    }

    private function getScans()
    {
        if ($this->dbConnection) {
            $scans = $this->dbConnection->query("SELECT * FROM scan_ids ORDER BY date DESC;");
            if (!isset($scans['databaseQueryError'])) {
                $this->response = ['scans' => $scans];
                return;
            }
        }
        $this->response = ['scans' => []];
    }

    private function getScanLocation()
    {
        $scanLocation = dirname($this->uciGet("pineap.@config[0].recon_db_path"));
        $this->response = ["success" => true, "scanLocation" => "{$scanLocation}/"];
    }

    private function setScanLocation()
    {
        $scanLocation = $this->request->scanLocation;
        if (!empty($scanLocation)) {
            $dbLocation = dirname("{$scanLocation}/fake_file");
            $this->uciSet("pineap.@config[0].recon_db_path", "{$dbLocation}/recon.db");
            if ($this->checkPineAPDaemon()) {
                exec("/etc/init.d/pineapd stop");
                $this->startPineAPDaemon();
            }
            $this->response = ["success" => true];
        } else {
            $this->error = "You cannot specify an empty path.";
        }
    }

    private function removeScan()
    {
        $this->dbConnection->exec("DELETE FROM clients WHERE scan_id='%s';", $this->request->scanID);
        $this->dbConnection->exec("DELETE FROM aps WHERE scan_id='%s';", $this->request->scanID);
        $this->dbConnection->exec("DELETE FROM scan_ids WHERE scan_id='%s';", $this->request->scanID);

        $this->response = ["success" => true];
    }

    private function getWSAuthToken()
    {
        @$wsAuthToken = file_get_contents('/tmp/reconpp.token');
        if ($wsAuthToken === false) {
            $this->response = ["success" => false];
        } else {
            $this->response = ["success" => true, "wsAuthToken" => $wsAuthToken];
        }
    }
}