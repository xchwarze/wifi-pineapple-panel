<?php namespace pineapple;

class Logging extends SystemModule
{
    public function route()
    {
        switch ($this->request->action) {
            case 'getSyslog':
                $this->getSyslog();
                break;

            case 'getDmesg':
                $this->getDmesg();
                break;

            case 'getReportingLog':
                $this->getReportingLog();
                break;

            case 'getPineapLog':
                $this->getPineapLog();
                break;

            case 'clearPineapLog':
                $this->clearPineapLog();
                break;

            case 'getPineapLogLocation':
                $this->getPineapLogLocation();
                break;

            case 'setPineapLogLocation':
                $this->setPineapLogLocation();
                break;

            case 'downloadPineapLog':
                $this->downloadPineapLog();
                break;
        }
    }

    private function downloadPineapLog()
    {
        $dbLocation = $this->uciGet("pineap.@config[0].hostapd_db_path");
        $db = new DatabaseConnection($dbLocation);
        $rows = $db->query("SELECT * FROM log ORDER BY updated_at ASC;");
        $logFile = fopen("/tmp/pineap.log", 'w');
        $count = "-";
        foreach ($rows as $row) {
            switch ($row['log_type']) {
                case 0:
                    $type = "Probe Request";
                    $count = $row['dups'];
                    break;
                case 1:
                    $type = "Association";
                    break;
                case 2:
                    $type = "De-association";
                    break;
                default:
                    $type = "";
                    break;
            }
            fwrite($logFile, "${row['created_at']},\t${type},\t${row['mac']},\t${row['ssid']},\t${count}\n");
        }
        fclose($logFile);
        $this->response = array("download" => $this->downloadFile('/tmp/pineap.log'));
    }

    private function getSyslog()
    {
        exec("logread", $syslogOutput);
        $this->response = implode("\n", $syslogOutput);
    }

    private function getDmesg()
    {
        exec("dmesg", $dmesgOutput);
        $this->response = implode("\n", $dmesgOutput);
    }

    private function getReportingLog()
    {
        touch('/tmp/reporting.log');
        $this->streamFunction = function () {
            $fp = fopen('/tmp/reporting.log', 'r');
            while (($buf = fgets($fp)) !== false) {
                echo $buf;
            }
            fclose($fp);
        };
    }

    private function getPineapLog()
    {
        $dbLocation = $this->uciGet("pineap.@config[0].hostapd_db_path");
        $db = new DatabaseConnection($dbLocation);
        $rows = $db->query("SELECT * FROM log ORDER BY updated_at DESC;");
        $this->response = array("pineap_log" => $rows);
    }

    private function clearPineapLog()
    {
        $dbLocation = $this->uciGet("pineap.@config[0].hostapd_db_path");
        $db = new DatabaseConnection($dbLocation);
        $db->exec("DELETE FROM log;");
        $this->response = array('success' => true);
    }

    private function getPineapLogLocation()
    {
        $dbBasePath = dirname($this->uciGet("pineap.@config[0].hostapd_db_path"));
        $this->response = array('location' => $dbBasePath . "/");
    }

    private function setPineapLogLocation()
    {
        $dbLocation = dirname($this->request->location . '/fake_file');
        $this->uciSet("pineap.@config[0].hostapd_db_path", $dbLocation . '/log.db');
        $this->response = array('success' => true);
    }
}
