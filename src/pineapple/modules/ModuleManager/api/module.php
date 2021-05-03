<?php namespace pineapple;

class ModuleManager extends SystemModule
{
    public function route()
    {
        switch ($this->request->action) {
            case 'getAvailableModules':
                $this->getAvailableModules();
                break;

            case 'getInstalledModules':
                $this->getInstalledModules();
                break;

            case 'installModule':
                $this->installModule();
                break;

            case 'downloadModule':
                $this->downloadModule();
                break;

            case 'checkDestination':
                $this->checkDestination();
                break;

            case 'removeModule':
                $this->removeModule();
                break;

            case 'downloadStatus':
                $this->downloadStatus();
                break;

            case 'installStatus':
                $this->installStatus();
                break;

            case 'restoreSDcardModules':
                if ($this->sdReaderPresent()) {
                    $this->restoreSDcardModules();
                }
                break;
        }
    }

    private function getAvailableModules()
    {
        $device = $this->getDevice();
        $moduleData = @file_get_contents("https://www.wifipineapple.com/{$device}/modules");

        if ($moduleData !== false) {
            $moduleData = json_decode($moduleData);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->response = array('availableModules' => $moduleData);
            }
        } else {
            $this->error = 'Error connecting to WiFiPineapple.com. Please check your connection.';
        }
    }

    private function getInstalledModules()
    {
        $modules = array();
        $modulesDirectories = scandir('/pineapple/modules');
        foreach ($modulesDirectories as $moduleDirectory) {
            if ($moduleDirectory[0] === ".") {
                continue;
            }

            if (file_exists("/pineapple/modules/{$moduleDirectory}/module.info")) {
                $moduleData = json_decode(file_get_contents("/pineapple/modules/{$moduleDirectory}/module.info"));
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                $module = array();
                $module['title'] = $moduleData->title;
                $module['author'] = $moduleData->author;
                $module['version'] = $moduleData->version;
                $module['description'] = $moduleData->description;
                $module['size'] = exec("du -sh /pineapple/modules/$moduleDirectory/ | awk '{print $1;}'");
                $module['checksum'] = $moduleData->checksum;
                if (isset($moduleData->system)) {
                    $module['type'] = "System";
                } elseif (isset($moduleData->cliOnly)) {
                    $module['type'] = "CLI";
                } else {
                    $module['type'] = "GUI";
                }

                $modules[$moduleDirectory] = $module;
            }
        }
        $this->response = array("installedModules" => $modules);
    }

    private function downloadModule()
    {
        @unlink('/tmp/moduleDownloaded');

        if ($this->request->destination === 'sd') {
            @mkdir('/sd/tmp/');
            $dest = '/sd/tmp/';
        } else {
            $dest = '/tmp/';
        }

        $device = $this->getDevice();
        $this->execBackground("wget 'https://www.wifipineapple.com/{$device}/modules/{$this->request->moduleName}' -O {$dest}{$this->request->moduleName}.tar.gz && touch /tmp/moduleDownloaded");
        $this->response = array('success' => true);
    }

    private function downloadStatus()
    {
        if (file_exists('/tmp/moduleDownloaded')) {
            if ($this->request->destination === 'sd') {
                $dest = '/sd/tmp/';
            } else {
                $dest = '/tmp/';
            }

            if (hash_file('sha256', "{$dest}{$this->request->moduleName}.tar.gz") == $this->request->checksum) {
                $this->response = array('success' => true);
                return;
            }
        }
        $this->response = array('success' => false);
    }

    private function installModule()
    {
        @unlink('/tmp/moduleInstalled');
        $this->removeModule();

        if ($this->request->destination === 'sd') {
            @mkdir('/sd/modules/');
            $dest = '/sd/tmp/';
            $installDest = '/sd/modules/';
            exec("ln -s /sd/modules/{$this->request->moduleName} /pineapple/modules/{$this->request->moduleName}");
        } else {
            $dest = '/tmp/';
            $installDest = '/pineapple/modules/';
        }

        $this->execBackground("tar -xzvC {$installDest} -f {$dest}{$this->request->moduleName}.tar.gz && rm {$dest}{$this->request->moduleName}.tar.gz && touch /tmp/moduleInstalled");
        $this->response = array('success' => true);
    }

    private function installStatus()
    {
        $this->response = array('success' => file_exists('/tmp/moduleInstalled'));
    }

    private function checkDestination()
    {
        $responseArray = array('module' => $this->request->name, 'internal' => false, 'sd' => false);

        if (disk_free_space('/') > ($this->request->size + 150000)) {
            $responseArray['internal'] = true;
        }

        if ($this->isSDAvailable()) {
            $responseArray['sd'] = true;
        }

        $this->response = $responseArray;
    }

    private function removeModule()
    {
        if (is_link("/pineapple/modules/{$this->request->moduleName}")) {
            @unlink("/pineapple/modules/{$this->request->moduleName}");
            exec("rm -rf /sd/modules/{$this->request->moduleName}");
        } else {
            exec("rm -rf /pineapple/modules/{$this->request->moduleName}");
        }

        $this->response = array('success' => true);
    }

    private function restoreSDcardModules()
    {
        $restored = false;
        $sdcardModules = @scandir('/sd/modules/');
        foreach ($sdcardModules as $module) {
            if ($module[0] != '.' && !file_exists("/pineapple/modules/{$module}")) {
                $restored = true;
                exec("ln -s /sd/modules/{$module} /pineapple/modules/{$module}");
            }
        }
        $this->response = array("restored" => $restored);
    }
}
