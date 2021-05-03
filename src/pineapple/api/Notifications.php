<?php namespace pineapple;

require_once('DatabaseConnection.php');

class Notifications extends APIModule
{

    private $notifications;
    private $dbConnection;
    const DATABASE = "/etc/pineapple/pineapple.db";

    public function __construct($request)
    {
        parent::__construct($request);
        $this->dbConnection = new DatabaseConnection(self::DATABASE);
        if (!empty($this->dbConnection->error)) {
            $this->error = $this->dbConnection->strError();
            return;
        }
        $this->notifications = array();
        $this->dbConnection->exec("CREATE TABLE IF NOT EXISTS notifications (message VARCHAR NOT NULL, time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP);");
        if (!empty($this->dbConnection->error)) {
            $this->error = $this->dbConnection->strError();
        }
    }

    public function route()
    {
        switch ($this->request->action) {
            case 'listNotifications':
                $this->response = $this->getNotifications();
                break;
            case 'addNotification':
                $this->response = $this->addNotification($this->request->message);
                break;
            case 'clearNotifications':
                $this->response = $this->clearNotifications();
                break;
            default:
                $this->error = "Unknown action: " . $this->request->action;
        }
    }

    public function addNotification($message)
    {
        $result = $this->dbConnection->exec("INSERT INTO notifications (message) VALUES('%s');", $message);
        return $result;
    }

    public function getNotifications()
    {
        $result = $this->dbConnection->query("SELECT message,time from notifications ORDER BY time DESC;");
        $this->notifications = $result;
        return $this->notifications;
    }

    public function clearNotifications()
    {
        $result = $this->dbConnection->exec('DELETE FROM notifications;');
        unset($this->notifications);
        return $result;
    }
}
