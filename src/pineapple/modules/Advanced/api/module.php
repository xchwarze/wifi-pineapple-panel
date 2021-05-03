<?php namespace pineapple;

class Advanced extends SystemModule
{
    private $dbConnection;

    const DATABASE = "/etc/pineapple/pineapple.db";

    public function __construct($request)
    {
        parent::__construct($request, __CLASS__);
        $this->dbConnection = new DatabaseConnection(self::DATABASE);
        $this->dbConnection->exec("CREATE TABLE IF NOT EXISTS api_tokens (token VARCHAR NOT NULL, name VARCHAR NOT NULL);");
    }

    public function route()
    {
        switch ($this->request->action) {
            case 'getResources':
                $this->getResources();
                break;

            case 'dropCaches':
                $this->dropCaches();
                break;

            case 'getUSB':
                $this->getUSB();
                break;

            case 'getFstab':
                $this->getFstab();
                break;

            case 'saveFstab':
                $this->saveFstab();
                break;

            case 'getCSS':
                $this->getCSS();
                break;

            case 'saveCSS':
                $this->saveCSS();
                break;

            case 'formatSDCard':
                if ($this->sdReaderPresent()) {
                    $this->formatSDCard();
                }
                break;

            case 'formatSDCardStatus':
                $this->formatSDCardStatus();
                break;

            case 'checkForUpgrade':
                $this->checkForUpgrade();
                break;

            case 'downloadUpgrade':
                $this->downloadUpgrade();
                break;

            case 'getDownloadStatus':
                $this->getDownloadStatus();
                break;

            case 'performUpgrade':
                $this->performUpgrade();
                break;

            case 'getCurrentVersion':
                $this->getCurrentVersion();
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

            case 'revokeApiToken':
                $this->revokeApiToken();
                break;
        }
    }

    private function getResources()
    {
        exec('df -h', $freeDisk);
        $freeDisk = implode("\n", $freeDisk);

        exec('free -m', $freeMem);
        $freeMem = implode("\n", $freeMem);

        $this->response = array("freeDisk" => $freeDisk, "freeMem" => $freeMem);
    }

    private function dropCaches()
    {
        $this->execBackground('echo 3 > /proc/sys/vm/drop_caches');
        $this->response = array('success' => true);
    }

    private function getUSB()
    {
        exec('lsusb', $lsusb);
        $lsusb = implode("\n", $lsusb);

        $this->response = array('lsusb' => $lsusb);
    }

    private function getFstab()
    {
        $fstab = file_get_contents('/etc/config/fstab');
        $this->response = array('fstab' => $fstab);
    }

    private function saveFstab()
    {
        if (isset($this->request->fstab)) {
            file_put_contents('/etc/config/fstab', $this->request->fstab);
            $this->response = array("success" => true);
        }
    }

    private function getCSS()
    {
        $css = file_get_contents('/pineapple/css/main.css');
        $this->response = array('css' => $css);
    }

    private function saveCSS()
    {
        if (isset($this->request->css)) {
            file_put_contents('/pineapple/css/main.css', $this->request->css);
            $this->response = array("success" => true);
        }
    }

    private function checkForUpgrade()
    {
        $device = $this->getDevice();
        $upgradeData = @file_get_contents("https://www.wifipineapple.com/{$device}/upgrades");

        if ($upgradeData !== false) {
            $upgradeData = json_decode($upgradeData);
            if (json_last_error() === JSON_ERROR_NONE) {
                if ($this->compareFirmwareVersion($upgradeData->version) === true) {
                    if ($upgradeData->hotpatch != null) {
                        $hotpatch = base64_decode($upgradeData->hotpatch);
                        file_put_contents($hotpatch, "/tmp/hotpatch.patch");
                    }
                    $this->response = array("upgrade" => true, "upgradeData" => $upgradeData);
                } else {
                    $this->error = "No upgrade found.";
                }
            }
        } else {
            $this->error = "Error connecting to WiFiPineapple.com. Please check your connection.";
        }

    }

    private function downloadUpgrade()
    {
        if (file_exists('/tmp/hotpatch.patch')) {
            exec("cd / && patch < /tmp/hotpatch.patch");
        }
        $version = $this->request->version;
        $device = $this->getDevice();
        @unlink("/tmp/upgrade.bin");
        @unlink("/tmp/upgradeDownloaded");
        $this->execBackground("wget 'https://www.wifipineapple.com/{$device}/upgrades/{$version}' -O /tmp/upgrade.bin && touch /tmp/upgradeDownloaded");
        $this->response = array("success" => true);
    }

    private function getDownloadStatus()
    {
        if (file_exists("/tmp/upgradeDownloaded")) {
            if (hash_file('sha256', '/tmp/upgrade.bin') == $this->request->checksum) {
                $this->response = array("completed" => true);
            } else {
                $this->error = "Checksum mismatch";
            }
        } else {
            $this->response = array("completed" => false, "downloaded" => filesize('/tmp/upgrade.bin'));
        }
    }

    private function performUpgrade()
    {
        if (file_exists('/tmp/upgrade.bin')) {
            $size = escapeshellarg(filesize('/tmp/upgrade.bin') - 33);
            exec("dd if=/dev/null of=/tmp/upgrade.bin bs=1 seek={$size}");
            $this->execBackground("sysupgrade -n /tmp/upgrade.bin");
            $this->response = array("success" => true);
        } else {
            $this->error = "Upgrade failed.";
        }
    }

    private function compareFirmwareVersion($version)
    {
        return version_compare($this->getFirmwareVersion(), $version, '<');
    }

    private function getCurrentVersion()
    {
        $this->response = array("firmwareVersion" => $this->getFirmwareVersion());
    }

    private function formatSDCard()
    {
        $this->execBackground("/pineapple/modules/Advanced/formatSD/format_sd");
        $this->response = array('success' => true);
    }

    private function formatSDCardStatus()
    {
        if (!file_exists('/tmp/sd_format.progress')) {
            $this->response = array('success' => true);
        } else {
            $this->response = array('success' => false);
        }
    }

    private function getApiTokens()
    {
        $this->response = array("tokens" => $this->dbConnection->query("SELECT ROWID, token, name FROM api_tokens;"));
    }

    private function checkApiToken()
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

    private function addApiToken()
    {
        if (isset($this->request->name)) {
            $token = hash('sha512', openssl_random_pseudo_bytes(32));
            $name = $this->request->name;
            $this->dbConnection->exec("INSERT INTO api_tokens(token, name) VALUES('%s','%s');", $token, $name);
            $this->response = array("success" => true, "token" => $token, "name" => $name);
        } else {
            $this->error = "Missing token name";
        }
    }

    private function revokeApiToken()
    {
        if (isset($this->request->id)) {
            $this->dbConnection->exec("DELETE FROM api_tokens WHERE ROWID='%s'", $this->request->id);
        } elseif (isset($this->request->token)) {
            $this->dbConnection->exec("DELETE FROM api_tokens WHERE token='%s'", $this->request->token);
        } elseif (isset($this->request->name)) {
            $this->dbConnection->exec("DELETE FROM api_tokens WHERE name='%s'", $this->request->name);
        } else {
            $this->error = "The revokeApiToken API call requires either a 'id', 'token', or 'name' parameter";
        }
    }
}
