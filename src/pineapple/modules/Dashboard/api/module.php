<?php namespace pineapple;

require_once('/pineapple/api/DatabaseConnection.php');

class Dashboard extends SystemModule
{
    private $dbConnection;
    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = false;
        if (file_exists('/tmp/landingpage.db')) {
            $this->dbConnection = new DatabaseConnection('/tmp/landingpage.db');
        }
    }

    public function route()
    {
        switch ($this->request->action) {
            case 'getOverviewData':
                $this->getOverviewData();
                break;

            case 'getLandingPageData':
                $this->getLandingPageData();
                break;
            
            case 'getBulletins':
                $this->getBulletins();
                break;
        }
    }

    private function getOverviewData()
    {
        $this->response = [
            "cpu" => $this->getCpu(),
            "uptime" => $this->getUptime(),
            "clients" => $this->getClients()
        ];
    }

    private function getCpu()
    {
        $loads = sys_getloadavg();
        $load = round($loads[0]/2*100, 1);

        if ($load > 100) {
            return '100';
        }

        return $load;
    }

    private function getUptime()
    {
        $seconds = intval(explode('.', file_get_contents('/proc/uptime'))[0]);
        $days = floor($seconds / (24 * 60 * 60));
        $hours = floor(($seconds % (24 * 60 * 60)) / (60 * 60));
        if ($days > 0) {
            return $days . ($days == 1 ? " day, " : " days, ") . $hours . ($hours == 1 ? " hour" : " hours");
        }
        $minutes = floor(($seconds % (60 * 60)) / 60);
        return $hours . ($hours == 1 ? " hour, " : " hours, ") . $minutes . ($minutes == 1 ? " minute" : " minutes");
    }

    private function getClients()
    {
        return exec('iw dev wlan0 station dump | grep Station | wc -l');
    }

    private function getLandingPageData()
    {
        if ($this->dbConnection !== false) {
            $stats = [];
            $stats['Chrome'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'chrome\';'));
            $stats['Safari'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'safari\';'));
            $stats['Firefox'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'firefox\';'));
            $stats['Opera'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'opera\';'));
            $stats['Internet Explorer'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'internet_explorer\';'));
            $stats['Other'] = count($this->dbConnection->query('SELECT browser FROM user_agents WHERE browser=\'other\';'));
            $this->response = $stats;
        } else {
            $this->error = "A connection to the database is not established.";
        }
    }


    private function getBulletins()
    {
        $bulletinData = @$this->fileGetContentsSSL(self::REMOTE_URL . "/json/news.json");
        if ($bulletinData !== false) {
            $this->response = json_decode($bulletinData);
            if (json_last_error() === JSON_ERROR_NONE) {
                return;
            }
        }
        
        $this->error = "Error connecting to " . self::REMOTE_NAME . ". Please check your connection.";
    }
}
