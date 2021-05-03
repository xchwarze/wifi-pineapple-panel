registerController("AdvancedResourcesController", ['$api', '$scope', '$timeout', function($api, $scope, $timeout){
    $scope.freeDisk = "";
    $scope.freeMem = "";
    $scope.droppedCaches = false;
    $scope.device = undefined;

    $api.request({
        module: 'Advanced',
        action: 'getResources'
    }, function(response){
        $scope.freeDisk = response.freeDisk;
        $scope.freeMem = response.freeMem;
    });

    $scope.dropCaches = (function() {
        $api.request({
            module: 'Advanced',
            action: 'dropCaches'
        }, function(response) {
            if (response.success === true) {
                $scope.droppedCaches = true;
                $timeout(function(){
                    $scope.droppedCaches = false;
                }, 2000);
            }
        });
    });

    $scope.getDevice = (function() {
        $api.request({
            module: 'Configuration',
            action: 'getDevice'
        }, function(response) {
            $scope.device = response.device;
        });
    });
    $scope.getDevice();

    $api.onDeviceIdentified(function(device, scope) {
        scope.device = device;
    }, $scope);
}]);

registerController("AdvancedUSBController", ['$api', '$scope', '$timeout', '$interval', function($api, $scope, $timeout, $interval){
    $scope.formattingSDCard = false;
    $scope.lsusb = "";
    $scope.fstab = "";
    $scope.fstabSaved = false;
    $scope.device = "";

    $scope.getDevice = (function() {
        $api.request({
            module: 'Configuration',
            action: 'getDevice'
        }, function(response) {
            $scope.device = response.device;
        });
    });
    $scope.getDevice();

    $scope.formatSDCard = (function() {
        $api.request({
            module: 'Advanced',
            action: 'formatSDCard'
        }, function(response){
            if (response.success === true) {
                $scope.formattingSDCard = true;

                $scope.SDCardInterval = $interval(function(){
                    $api.request({
                        module: 'Advanced',
                        action: 'formatSDCardStatus'
                    }, function(response) {
                        if (response.success === true){
                            $scope.formattingSDCard = false;
                            $scope.formatSuccess = true;
                            $interval.cancel($scope.SDCardInterval);
                            $timeout(function(){
                                $scope.formatSuccess = false;
                            }, 2000);
                        }
                    });
                }, 5000);
            }
        });
    });

    $api.request({
        module: 'Advanced',
        action: 'getUSB'
    }, function(response){
        $scope.lsusb = response.lsusb;
    });

    $api.request({
        module: 'Advanced',
        action: 'getFstab'
    }, function(response) {
        if (response.error === undefined) {
            $scope.fstab = response.fstab;
        }
    });

    $scope.saveFstab = (function() {
        $api.request({
            module: 'Advanced',
            action: 'saveFstab',
            fstab: $scope.fstab
        }, function(response) {
            if (response.success === true) {
                $scope.fstabSaved = true;
                $timeout(function(){
                    $scope.fstabSaved = false;
                }, 2000);
            }
        });
    });

    $scope.$on('$destroy', function() {
        $interval.cancel($scope.SDCardInterval);
    });
}]);

registerController("AdvancedCSSController", ['$api', '$scope', '$timeout', function($api, $scope, $timeout){
    $scope.css = "";
    $scope.cssSaved = false;

    $api.request({
        module: 'Advanced',
        action: 'getCSS'
    }, function(response) {
        if (response.error === undefined) {
            $scope.css = response.css;
        }
    });

    $scope.saveCSS = (function() {
        $api.request({
            module: 'Advanced',
            action: 'saveCSS',
            css: $scope.css
        }, function(response) {
            if (response.success === true) {
                $scope.cssSaved = true;
                $timeout(function(){
                    $scope.cssSaved = false;
                }, 2000);
            }
        });
    });
}]);

registerController("AdvancedUpgradeController", ['$api', '$scope', '$interval', function($api, $scope, $interval){
    $scope.error = "";
    $scope.loading = false;
    $scope.upgradeFound = false;
    $scope.downloadInterval = false;
    $scope.downloading = false;
    $scope.downloaded = false;
    $scope.upgradeData = {};
    $scope.downloadPercentage = 0;
    $scope.firmwareVersion = "";

    $api.request({
        module: 'Advanced',
        action: 'getCurrentVersion'
    }, function(response) {
        if (response.error === undefined) {
            $scope.firmwareVersion = response.firmwareVersion;
        }
    });

    $scope.checkForUpgrade = (function() {
        $scope.loading = true;
        $api.request({
            module: 'Advanced',
            action: 'checkForUpgrade'
        }, function(response) {
            $scope.loading = false;
            if (response.error) {
                $scope.error = response.error;
            } else if (response.upgrade) {
                $scope.upgradeFound = true;
                $scope.upgradeData = response.upgradeData;
                $scope.error = false;
            }
        });
    });

    $scope.downloadUpgrade = (function() {
        $api.request({
            module: 'Advanced',
            action: 'downloadUpgrade',
            version: $scope.upgradeData['version']
        }, function(response) {
            if (response.success === true) {
                $scope.downloading = true;
                $scope.downloadInterval = $interval(function() {
                    $scope.getDownloadStatus();
                }, 1000);
            }
        });
    });

    $scope.getDownloadStatus = (function() {
        $api.request({
            module: 'Advanced',
            action: 'getDownloadStatus',
            checksum: $scope.upgradeData['checksum']
        }, function(response) {
            if ($scope.downloaded) return;
            if (response.completed === true) {
                $scope.downloading = false;
                $scope.downloaded = true;
                $interval.cancel($scope.downloadInterval);
                $scope.performUpgrade();
            } else if (response.error) {
                $scope.error = response.error;
            } else {
                $scope.downloadPercentage = Math.round((response.downloaded / $scope.upgradeData['size']) * 100);
            }
        });
    });

    $scope.performUpgrade = (function() {
        $api.request({
            module: 'Advanced',
            action: 'performUpgrade'
        }, function(response) {
            if (response.success === true) {
            }
        });
    });

    $scope.$on('$destroy', function() {
        $interval.cancel($scope.downloadInterval);
    });
}]);

registerController("APITokenController", ['$api', '$scope', function($api, $scope) {
    $scope.apiTokens = [];
    $scope.newToken = {
        name: "",
        token: ""
    };

    $scope.getApiTokens = function(){
        $api.request({
            module: 'Advanced',
            action: 'getApiTokens'
        }, function(response){
            $scope.apiTokens = response.tokens;
        });
    };

    $scope.genApiToken = function(){
        $api.request({
            module: 'Advanced',
            action: 'addApiToken',
            name: $scope.newToken.name
        }, function(response){
            $scope.newToken.name = "";
            $scope.newToken.token = response.token;
            $scope.getApiTokens();
        });
    };

    $scope.revokeApiToken = function($event){
        var id = $event.target.getAttribute('tokenid');
        $api.request({
            module: 'Advanced',
            action: 'revokeApiToken',
            id: id
        }, function(){
            $scope.getApiTokens();
        });
    };

    $scope.selectElem = function(elem){
        var selectRange = document.createRange();
        selectRange.selectNodeContents(elem);
        var selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(selectRange);
    };

    $scope.selectOnClick = function($event){
        var elem = $event.target;
        $scope.selectElem(elem);
    };

    $scope.getApiTokens();
}]);
