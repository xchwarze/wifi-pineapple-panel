registerController("ModuleManagerController", ['$api', '$scope', '$timeout', '$interval', '$templateCache', '$rootScope', function($api, $scope, $timeout, $interval, $templateCache, $rootScope){
    $rootScope.availableModules = [];
    $rootScope.installedModules = [];
    $scope.installedModule = "";
    $scope.removedModule = "";
    $scope.gotAvailableModules = false;
    $scope.connectionError = false;
    $scope.selectedModule = false;
    $scope.downloading = false;
    $scope.installing = false;
    $scope.linking = true;
    $scope.device = undefined;

    $scope.getDevice = (function() {
        $api.request({
            module: "Configuration",
            action: "getDevice"
        }, function(response) {
            $scope.device = response.device;
        });
    });
    $scope.getDevice();

    $scope.getAvailableModules = (function() {
        $scope.loading = true;
        $api.request({
            module: "ModuleManager",
            action: "getAvailableModules"
        }, function(response) {
            $scope.loading = false;
            if (response.error === undefined) {
                $rootScope.availableModules = response.availableModules;
                $scope.compareModuleLists();
                $scope.gotAvailableModules = true;
                $scope.connectionError = false;
            } else {
                $scope.connectionError = response.error;
            }
        });
    });

    $scope.getInstalledModules = (function() {
        $api.request({
            module: "ModuleManager",
            action: "getInstalledModules"
        }, function(response) {
            $rootScope.installedModules = response.installedModules;
            if ($scope.gotAvailableModules) {
                $scope.compareModuleLists();
            }
        });
    });

    $scope.compareModuleLists = (function() {
        angular.forEach($rootScope.availableModules, function(module, moduleName){
            if ($rootScope.installedModules[moduleName] === undefined){
                module['installable'] = true;
            } else if ($rootScope.availableModules[moduleName].version <= $rootScope.installedModules[moduleName].version) {
                module['installed'] = true;
            }
        });
    });

    $scope.checkDestination = (function(moduleName, moduleSize) {
        $(window).scrollTop(0);

        if ($rootScope.installedModules[moduleName] !== undefined && $rootScope.installedModules[moduleName]['type'] === 'System') {
            $scope.selectedModule = {module: moduleName, internal: true, sd: false};
            return;
        }

        if ($scope.device === 'tetra') {
            $scope.selectedModule = {module: moduleName, internal: true, sd: false};
            $scope.downloadModule('internal');
            return;
        }

        $api.request({
            module: 'ModuleManager',
            action: 'checkDestination',
            name: moduleName,
            size: moduleSize
        }, function(response) {
            if (response.error === undefined) {
                $scope.selectedModule = response;
            }
        });
    });

    $scope.removeModule = (function(name) {
        $api.request({
            module: 'ModuleManager',
            action: 'removeModule',
            moduleName: name
        }, function(response) {
            if (response.success === true) {
                $scope.getInstalledModules();
                $scope.removedModule = true;
                $api.reloadNavbar();
                $timeout(function(){
                    $scope.removedModule = false;
                }, 2000);
            }
        });
    });

    $scope.restoreSDcardModules = (function() {
        $api.request({
            module: 'ModuleManager',
            action: 'restoreSDcardModules'
        }, function(response) {
            if (response.restored === true) {
                $scope.restoreSDcardModules();
            } else {
                $api.reloadNavbar();
                $scope.getInstalledModules();
                $scope.linking = false;
            }
        });
    });

    if ($scope.device === 'nano') {
        $scope.restoreSDcardModules();
    } else {
        $scope.linking = false;
    }
    $scope.getInstalledModules();
}]);
