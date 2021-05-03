<?php namespace pineapple;

require_once('DatabaseConnection.php');

class Authentication extends APIModule
{
    private $dbConnection;

    const DATABASE = "/etc/pineapple/pineapple.db";

    public function __construct($request)
    {
        parent::__construct($request);
        $this->dbConnection = new DatabaseConnection(self::DATABASE);
        $this->dbConnection->exec("CREATE TABLE IF NOT EXISTS api_tokens (token VARCHAR NOT NULL, name VARCHAR NOT NULL);");
    }

    public function getApiTokens()
    {
        $this->response = array("tokens" => $this->dbConnection->query("SELECT token,name FROM api_tokens;"));
    }

    public function checkApiToken()
    {
        if (isset($this->request->token)) {
            $token = $this->request->token;
            $result = $this->dbConnection->query("SELECT token FROM api_tokens WHERE token='%s';", $token);
            if (!empty($result) && isset($result[0]["token"]) && $result[0]["token"] === $token) {
                $this->response = array("valid" => true);
            }
        }
        $this->response = array("valid" => false);
    }

    public function addApiToken()
    {
        if (isset($this->request->token) && isset($this->request->name)) {
            $token = $this->request->token;
            $name = $this->request->name;
            $this->dbConnection->exec("INSERT INTO api_tokens(token, name) VALUES('%s','%s');", $token, $name);
            $this->response = array("success" => true);
        } else {
            $this->error = "Missing token or name";
        }
    }

    private function login()
    {
        if (isset($this->request->username) && isset($this->request->password)) {
            if ($this->verifyPassword($this->request->password)) {
                $_SESSION['logged_in'] = true;
                $this->response = array("logged_in" => true);
                if (!isset($this->request->time)) {
                    return;
                }
                $epoch = intval($this->request->time);
                if (is_int($epoch) && $epoch > 1) {
                    exec('date -s @' . $epoch);
                }
                return;
            }
        }

        $this->response = array("logged_in" => false);
    }

    private function verifyPassword($password)
    {
        $shadowContents = file_get_contents('/etc/shadow');
        $rootArray = explode(':', explode('root:', $shadowContents)[1]);
        $rootPass = $rootArray[0];
        if ($rootPass != null && !empty($rootPass) && gettype($rootPass) === "string") {
            return hash_equals($rootPass, crypt($password, $rootPass));
        }
        return false;
    }

    private function logout()
    {
        $this->response = array("logged_in" => false);
        unset($_COOKIE['XSRF-TOKEN']);
        setcookie('XSRF-TOKEN', '', time()-3600);
        unset($_SESSION['XSRF-TOKEN']);
        unset($_SESSION['logged_in']);
        session_destroy();
    }

    private function checkAuth()
    {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            $this->response = array("authenticated" => true);
        } else {
            if (file_exists("/etc/pineapple/setupRequired")) {
                $this->response = array("error" => "Not Authenticated", "setupRequired" => true);
            } else {
                $this->response = array("error" => "Not Authenticated");
            }
        }
    }

    public function route()
    {
        switch ($this->request->action) {
            case 'login':
                $this->login();
                break;

            case 'logout':
                $this->logout();
                break;

            case 'checkAuth':
                $this->checkAuth();
                break;

            case 'checkApiToken':
                $this->checkApiToken();
                break;

            case 'addApiToken':
                $this->addApiToken();
                break;

            case 'getApiTokens':
                $this->getApiTokens();
                break;

            default:
                $this->error = "Unknown action";
        }
        
        session_write_close();
    }
}
