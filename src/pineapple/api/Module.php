<?php namespace pineapple;

/**
 * This class will contain the base code which all modules
 * must extend.
 */

abstract class Module
{
    protected $request;
    protected $response;
    protected $moduleClass;
    protected $error;
    protected $streamFunction;

    abstract public function route();

    public function __construct($request, $moduleClass)
    {
        $this->request = $request;
        $this->moduleClass = $moduleClass;
        $this->error = '';
    }

    public function getResponse()
    {
        if (empty($this->error) && !empty($this->response)) {
            return $this->response;
        } elseif (!empty($this->streamFunction)) {
            header('Content-Type: text/plain');
            $this->streamFunction->__invoke();
            return false;
        } elseif (empty($this->error) && empty($this->response)) {
            return array('error' => 'Module returned empty response');
        } else {
            return array('error' => $this->error);
        }
    }

    public function execBackground($command)
    {
        return \helper\execBackground($command);
    }

    protected function isSDAvailable()
    {
        return \helper\isSDAvailable();
    }

    protected function sdReaderPresent() {
        return \helper\sdReaderPresent();
    }

    protected function sdCardPresent() {
        return \helper\sdCardPresent();
    }

    protected function checkRunning($processName)
    {
        return \helper\checkRunning($processName);
    }

    protected function checkRunningFull($processString) {
        return \helper\checkRunningFull($processString);
    }

    public function uciGet($uciString)
    {
        return \helper\uciGet($uciString);
    }

    public function uciSet($settingString, $value)
    {
       \helper\uciSet($settingString, $value);
    }

    public function uciAddList($settingString, $value)
    {
       \helper\uciAddList($settingString, $value);
    }

    protected function downloadFile($file)
    {
        return \helper\downloadFile($file);
    }

    protected function getFirmwareVersion()
    {
        return \helper\getFirmwareVersion();
    }

    protected function getDevice()
    {
        return \helper\getDevice();
    }

    protected function getMacFromInterface($interface)
    {
        return \helper\getMacFromInterface($interface);
    }

    protected function udsSend($path, $message)
    {
        return \helper\udsSend($path, $message);
    }

    protected function dgramUdsSend($path, $message)
    {
        return \helper\dgramUdsSend($path, $message);
    }

    protected function installDependency($dependencyName, $installToSD = false)
    {
        if ($installToSD && !$this->isSDAvailable()) {
            return false;
        }

        $destination = $installToSD ? '--dest sd' : '';
        $dependencyName = escapeshellarg($dependencyName);

        if (!$this->checkDependency($dependencyName)) {
            exec("opkg update");
            exec("opkg install {$dependencyName} {$destination}");
        }
        return $this->checkDependency($dependencyName);
    }

    protected function checkDependency($dependencyName)
    {
        return \helper\checkDependency($dependencyName);
    }
}
