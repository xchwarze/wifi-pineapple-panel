<?php namespace pineapple;

require_once('DatabaseConnection.php');

class API
{

    private $request;
    private $response;
    private $error;
    private $dbConnection;
    const DATABASE = "/etc/pineapple/pineapple.db";

    /**
     * The constructor parses the JSON data from PHP's input.
     * Notify the user of errors.
     */
    public function __construct()
    {
        $this->request = @json_decode(file_get_contents('php://input'));
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error = 'Invalid JSON';
        }
        $this->dbConnection = new DatabaseConnection(self::DATABASE);
        $this->setCSRFToken();
    }

    public function setCSRFToken()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['XSRF-TOKEN'])) {
            $_SESSION['XSRF-TOKEN'] = sha1(session_id() . openssl_random_pseudo_bytes(16));
        }
        if (!isset($_COOKIE['XSRF-TOKEN']) || $_COOKIE['XSRF-TOKEN'] !== $_SESSION['XSRF-TOKEN']) {
            setcookie('XSRF-TOKEN', $_SESSION['XSRF-TOKEN'], 0, '/', '', false, false);
        }
    }

    /**
     * Checks if the user is currently authenticated
     * @return boolean
     */
    public function authenticated()
    {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            if (isset($_SERVER['HTTP_X_XSRF_TOKEN']) && $_SERVER['HTTP_X_XSRF_TOKEN'] === $_SESSION['XSRF-TOKEN']) {
                return true;
            } else {
                $this->error = "Invalid CSRF token";
                return false;
            }
        } elseif (isset($this->request->system) && $this->request->system === 'authentication') {
            if (isset($this->request->action) && $this->request->action === 'login') {
                return true;
            }
        } elseif (isset($this->request->system) && $this->request->system === 'setup') {
            if (file_exists('/etc/pineapple/setupRequired')) {
                return true;
            }
        } elseif (isset($this->request->apiToken)) {
            $token = $this->request->apiToken;
            $result = $this->dbConnection->query("SELECT token FROM api_tokens WHERE token='%s';", $token);
            if (!empty($result) && isset($result[0]["token"]) && $result[0]["token"] === $token) {
                return true;
            }
        }
        if (file_exists('/etc/pineapple/setupRequired')) {
            $this->response = array('error' => 'Not Authenticated', 'setupRequired' => true);
        } else {
            $this->error = "Not Authenticated";
        }
        return false;
    }

    /**
     * Routes the API request to the appropriate modules
     * @return void
     */
    public function route()
    {
        if (isset($this->request->system) && !empty($this->request->system)) {
            $this->routeToSystem($this->request->system);
        } elseif (isset($this->request->module) && !empty($this->request->module)) {
            $this->routeToModule($this->request->module);
        } else {
            $this->error = "Invalid request";
        }
    }

    /**
     * Function to finalize API and form the JSON return string
     * @return String JSON String
     */
    public function finalize()
    {
        if ($this->error) {
            return ")]}',\n" . json_encode(array("error" => $this->error));
        } elseif ($this->response) {
            return ")]}',\n" . json_encode($this->response);
        }
        return "";
    }

    /**
    * Function to lazy load a module class given a module name
    * @param String $moduleName The module Name
    * @return String The class of the module just loaded
    */
    private function lazyLoad($moduleName)
    {
        require_once("Module.php");
        require_once("SystemModule.php");

        $found = false;
        $moduleClass = "";

        foreach (glob('/pineapple/modules/*') as $moduleFolder) {
            if (str_replace('/pineapple/modules/', '', $moduleFolder) === $moduleName) {
                $found = true;
                require_once("$moduleFolder/api/module.php");
                $moduleClass = "pineapple\\{$moduleName}";
            }
        }

        if (!$found) {
            $this->error = "Module {$moduleName} does not exist or is defined incorrectly";
            return null;
        }
        if (!class_exists($moduleClass)) {
            $this->error = "The class {$moduleClass} does not exist in {$moduleFolder}";
            return null;
        }

        return $moduleClass;
    }

    /**
     * Function to route a module request to
     * it's appropriate module.
     * @param  String $moduleName The module Name
     * @return void
     */
    private function routeToModule($moduleName)
    {
        session_write_close();

        $moduleClass = $this->lazyLoad($moduleName);
        if ($moduleClass === null) {
            return;
        }

        $module = new $moduleClass($this->request, $moduleClass);
        $module->route();
        $this->response = $module->getResponse();
    }

    /**
     * Function to route a system request to the
     * appropriate component.
     * @param  String $systemRequest The system request
     * @return void
     */
    private function routeToSystem($systemRequest)
    {
        require_once("APIModule.php");
        $systemComponent = null;
        switch ($systemRequest) {
            case 'notifications':
                require_once("Notifications.php");
                $systemComponent = new Notifications($this->request);
                break;

            case 'modules':
                require_once("Modules.php");
                $systemComponent = new Modules($this->request);
                break;

            case 'authentication':
                require_once("Authentication.php");
                $systemComponent = new Authentication($this->request);
                break;
            case 'setup':
                if (file_exists('Setup.php')) {
                    require_once('Setup.php');
                    $systemComponent = new Setup($this->request);
                    break;
                }
        }

        if ($systemComponent !== null) {
            $systemComponent->route();
            $this->response = $systemComponent->getResponse();
        }
    }

    private function handleDownload()
    {
        $this->dbConnection->exec("CREATE TABLE IF NOT EXISTS downloads (token VARCHAR NOT NULL, file VARCHAR NOT NULL, time timestamp default (strftime('%s', 'now')));");
        $this->dbConnection->exec("DELETE FROM downloads WHERE time < (strftime('%s', 'now')-30)");
        $result = $this->dbConnection->query('SELECT file from downloads WHERE token="%s";', $_GET['download']);
        if (isset($result[0])) {
            $this->streamFile($result[0]['file']);
        } else {
            echo "Invalid download token.";
        }
        exit();
    }

    private function streamFile($file)
    {
        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file).'"');
            header('Expires: 0');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
        } else {
            echo "Invalid file.";
        }
        exit();
    }

    /**
    * Does magic
    */
    public function magic()
    {
        if (isset($_GET['download'])) {
            $this->handleDownload();
        } else {
            if ($this->authenticated()) {
                $this->route();
            }
            return $this->finalize();
        }

        return true;
    }
}
