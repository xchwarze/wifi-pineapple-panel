<?php namespace pineapple;

class Modules extends APIModule
{
    private $modules;

    public function __construct($request)
    {
        Parent::__construct($request);
        $this->modules = [
            'systemModules' => [],
            'userModules'   => []
        ];
    }

    public function getModules()
    {
        require_once('DatabaseConnection.php');

        $dir = scandir("../modules");
        if ($dir === false) {
            $this->error = "Unable to access modules directory";
            return $this->modules;
        }

        natcasesort($dir);
        foreach ($dir as $moduleFolder) {
            $modulePath = "../modules/{$moduleFolder}";
            if ($moduleFolder[0] === '.' || !file_exists("{$modulePath}/module.info")) {
                continue;
            }

            $moduleInfo = @json_decode(file_get_contents("{$modulePath}/module.info"));
            if (json_last_error() !== JSON_ERROR_NONE || isset($moduleInfo->cliOnly)) {
                continue;
            }

            $jsonModulePath = "/modules/${moduleFolder}";
            $module = [
                "name"     => $moduleFolder,
                "title"    => isset($moduleInfo->title) ? $moduleInfo->title : $moduleFolder,
                "icon"     => null,
                "injectJS" => isset($moduleInfo->injectJS) ? "${jsonModulePath}/{$moduleInfo->injectJS}" : null,
            ];

            if (file_exists("$modulePath/module_icon.svg")) {
                $module["icon"] = "${jsonModulePath}/module_icon.svg";
            } elseif (file_exists("$modulePath/module_icon.png")) {
                $module["icon"] = "${jsonModulePath}/module_icon.png";
            }

            if (isset($moduleInfo->system)) {
                if (isset($moduleInfo->index)) {
                    $this->modules['systemModules'][$moduleInfo->index] = $module;
                }
            } else {
                $this->modules['userModules'][] = $module;
            }
        }

        return $this->modules;
    }

    public function route()
    {
        switch ($this->request->action) {
            case "getModuleList":
                $this->getModules();
                $this->response = ['modules' => $this->modules];
                break;
            default:
                $this->error = "Unknown action: " . $this->request->action;
        }
    }
}
