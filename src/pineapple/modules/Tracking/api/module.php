<?php namespace pineapple;

class Tracking extends SystemModule
{
    const DATABASE = "/etc/pineapple/filters.db";

    private $dbConnection = null;

    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = false;
        if (file_exists(self::DATABASE)) {
            $this->dbConnection = new DatabaseConnection(self::DATABASE);
        }
    }

    public function route()
    {
        switch ($this->request->action) {
            case 'getScript':
                $this->getScript();
                break;

            case 'saveScript':
                $this->saveScript();
                break;

            case 'getTrackingList':
                $this->getTrackingList();
                break;

            case 'addMac':
                $this->addMac();
                break;

            case 'removeMac':
                $this->removeMac();
                break;

            case 'clearMacs':
                $this->clearMacs();
                break;
        }
    }

    private function getScript()
    {
        $trackingScript = file_get_contents("/etc/pineapple/tracking_script_user");
        $this->response = array("trackingScript" => $trackingScript);
    }

    private function saveScript()
    {
        if (isset($this->request->trackingScript)) {
            file_put_contents("/etc/pineapple/tracking_script_user", $this->request->trackingScript);
        }
        $this->response = array("success" => true);
    }

    private function getTrackingList()
    {
        $trackingList = "";
        $result = $this->dbConnection->query("SELECT mac FROM tracking;");

        foreach ($result as $row) {
            $trackingList .= $row['mac'] . "\n";
        }
        $this->response =  array("trackingList" => $trackingList);
    }

    private function addMac()
    {
        if (isset($this->request->mac) && !empty($this->request->mac)) {
            $mac = strtoupper($this->request->mac);
            if(preg_match('^[a-fA-F0-9:]{17}|[a-fA-F0-9]{12}^', $mac)) {
                $this->dbConnection->exec("INSERT INTO tracking (mac) VALUES ('%s');", $mac);
                $this->getTrackingList();
            } else {
                $this->error = "Please enter a valid MAC Address";
            }
        }
    }

    private function removeMac()
    {
        if (isset($this->request->mac) && !empty($this->request->mac)) {
            $mac = strtoupper($this->request->mac);
            if(preg_match('^[a-fA-F0-9:]{17}|[a-fA-F0-9]{12}^', $mac)) {
                $this->dbConnection->exec("DELETE FROM tracking WHERE mac='%s' COLLATE NOCASE;", $mac);
                $this->getTrackingList();
            } else {
                $this->error = "Please enter a valid MAC Address";
            }
        }
    }

    private function clearMacs()
    {
        $this->dbConnection->exec("DELETE FROM tracking;");
        $this->getTrackingList();
    }
}
