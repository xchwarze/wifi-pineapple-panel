<?php namespace pineapple;

class Modules extends APIModule
{
    private $modules;

    public function __construct($request)
    {
        Parent::__construct($request);
        $this->modules = array('systemModules' => array(), 'userModules' => array());
    }

    public function getModules()
    {
        require_once('DatabaseConnection.php');

        $dir = scandir("../modules");
        if ($dir == false) {
            $this->error = "Unable to access modules directory";
            return $this->modules;
        }

        natcasesort($dir);

        foreach ($dir as $moduleFolder) {
            if ($moduleFolder[0] === '.') {
                continue;
            }

            $modulePath = "../modules/{$moduleFolder}";

            if (!file_exists("{$modulePath}/module.info")) {
                continue;
            }

            $moduleInfo = @json_decode(file_get_contents("{$modulePath}/module.info"));
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $moduleTitle = (isset($moduleInfo->title)) ? $moduleInfo->title : $moduleFolder;

            if (file_exists("$modulePath/module_icon.svg")) {
                $module = array("name" => $moduleFolder, "title" => $moduleTitle, "icon" => "/modules/${moduleFolder}/module_icon.svg");
            } elseif (file_exists("$modulePath/module_icon.png")) {
                $module = array("name" => $moduleFolder, "title" => $moduleTitle, "icon" => "/modules/${moduleFolder}/module_icon.png");
            } else {
                $module = array("name" => $moduleFolder, "title" => $moduleTitle, "icon" => null);
            }

            if (isset($moduleInfo->system)) {
                if (!isset($moduleInfo->index)) {
                    continue;
                }
                $this->modules['systemModules'][$moduleInfo->index] = $module;
            } elseif (isset($moduleInfo->cliOnly)) {
                continue;
            } else {
                array_push($this->modules['userModules'], $module);
            }
        }

        return $this->modules;
    }

    public function route()
    {
        switch ($this->request->action) {
            case "getModuleList":
                $this->getModules();
                $this->response = array('modules' => $this->modules);
                break;
            default:
                $this->error = "Unknown action: " . $this->request->action;
        }
    }
}
