registerController('PineAPPoolController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.ssid = "";
    $scope.ssidPool = "";
    $scope.addedSSID = "";
    $scope.removedSSID = "";
    $scope.clearedSSIDPool = "";
    $scope.clearedSessionCounter = false;
    $scope.lengthError = "";
    $scope.poolLocation = "";

    $scope.downloadPool = (function() {
        $api.request({
            module: 'PineAP',
            action: 'downloadPineAPPool'
        }, function(response) {
            if (response.error === undefined) {
                window.location = '/api/?download=' + response.download;
            }
        });
    });

    $scope.addSSID = (function() {
        $api.request({
            module: 'PineAP',
            action: 'addSSID',
            ssid: $scope.ssid
        }, function(response) {
            if (response.error === undefined) {
                $scope.ssid = "";
                $scope.addedSSID = true;
            } else {
                $scope.lengthError = true;
            }
            $timeout(function(){
                $scope.addedSSID = false;
                $scope.lengthError = false;
            }, 2000);
            $scope.getPool();
        });
    });

    $scope.removeSSID = (function() {
        $api.request({
            module: 'PineAP',
            action: 'removeSSID',
            ssid: $scope.ssid
        }, function(response) {
            if (response.error === undefined) {
                $scope.ssid = "";
                $scope.removedSSID = true;
            } else {
                $scope.lengthError = true;
            }
            $timeout(function(){
                $scope.removedSSID = false;
                $scope.lengthError = false;
            }, 2000);
            $scope.getPool();
        });
    });

    $scope.getPoolLocation = (function() {
        $api.request({
            module: 'PineAP',
            action: 'getPoolLocation'
        }, function(response) {
            if (response.error === undefined) {
                $scope.poolLocation = response.poolLocation;
            }
        });
    });

    $scope.setPoolLocation = (function() {
        $api.request({
            module: 'PineAP',
            action: 'setPoolLocation',
            location: $scope.poolLocation
        }, function(response) {
            if (response.success === true) {
                $scope.getPoolLocation();
                $scope.getPool();
                $scope.updatedPoolLocation = true;
                $timeout(function(){
                    $scope.updatedPoolLocation = false;
                }, 2000);
            }
        });
    });

    $scope.getPool = (function() {
        $api.request({
            module: 'PineAP',
            action: 'getPool'
        }, function(response) {
            $scope.ssidPool = response.ssidPool;
        });
    });

    $scope.clearPool = (function() {
        $api.request({
            module: 'PineAP',
            action: 'clearPool'
        }, function(response) {
            if (response.success === true) {
                $scope.ssidPool = "";
                $scope.clearedSSIDPool = true;
                $timeout(function(){
                    $scope.clearedSSIDPool = false;
                }, 2000);
            }
        });
        $scope.getPool();
    });

    $scope.clearSessionCounter = (function() {
        $api.request({
            module: 'PineAP',
            action: 'clearSessionCounter'
        }, function(response) {
            if (response.success === true) {
                $scope.clearedSessionCounter = true;
                $timeout(function() {
                    $scope.clearedSessionCounter = false;
                }, 2000);
            }
        })
    });

    $scope.getSSIDLineNumber = function() {
        var textarea = $('#ssidPool');
        var lineNumber = textarea.val().substr(0, textarea[0].selectionStart).split("\n").length;
        var ssid = textarea.val().split("\n")[lineNumber-1].trim();
        $("input[name='ssid']").val(ssid).trigger('input');
    };

    $scope.getPool();
    $scope.getPoolLocation();
}]);

registerController('PineAPSettingsController', ['$api', '$scope', function($api, $scope) {
    $scope.disableButton = false;
    $scope.saveAlert = false;
    $scope.pineAPenabling = false;
    $scope.settings = {
        allowAssociations: false,
        logEvents: false,
        pineAPDaemon: false,
        autostartPineAP: false,
        beaconResponses: false,
        captureSSIDs: false,
        broadcastSSIDs: false,
        connectNotifications: false,
        disconnectNotifications: false,
        broadcastInterval: 'NORMAL',
        responseInterval: 'NORMAL',
        sourceMAC: '00:00:00:00:00:00',
        targetMAC: 'FF:FF:FF:FF:FF:FF'
    };

    $scope.togglePineAP = (function() {
        $scope.pineAPenabling = true;
        var actionString = $scope.settings.pineAPDaemon ? "disable" : "enable";
        $api.request({
            module: 'PineAP',
            action: actionString
        }, function(response) {
            if (response.error === undefined) {
                $scope.pineAPenabling = false;
                $scope.getSettings();
            }
        });
    });

    $scope.toggleAutoStart = (function() {
        var actionString = $scope.settings.autostartPineAP ? "disableAutoStart" : "enableAutoStart";
        $api.request({
            module: 'PineAP',
            action: actionString
        }, function(response) {
            if (response.error === undefined) {
                $scope.getSettings();
            }
        });
    });

    $scope.getSettings = function() {
        $api.request({
            module: 'PineAP',
            action: 'getPineAPSettings'
        }, function(response) {
            if (response.success === true) {
                $scope.settings = response.settings;
            }
        });
    };
    $scope.updateSettings = function() {
        $scope.disableButton = true;
        $api.request({
            module: 'PineAP',
            action: 'setPineAPSettings',
            settings: $scope.settings
        }, function() {
            $scope.getSettings();
            $scope.disableButton = false;
        });
    };

    $scope.getSettings();
}]);

registerController("PineAPEnterpriseController", ['$api', '$scope', '$timeout', '$interval', function($api, $scope, $timeout, $interval) {
    $scope.settings = {
        enabled: false,
        enableAssociations: false,
        ssid: "",
        mac: "",
        encryptionType: "wpa2+ccmp"
    };
    $scope.certificateSettings = {
        state: "California",
        country: "US",
        locality: "San Francisco",
        organization: "YOUR ORG",
        email: "bounce@example.com",
        commonname: "YOUR CERTIFICATE AUTHORITY"
    };
    $scope.loadingView = true;
    $scope.generatingCertificate = false;
    $scope.generateSuccess = false;
    $scope.savingSettings = false;
    $scope.savedSettings = false;
    $scope.certInstalled = false;
    $scope.data = [];
    $scope.error = '';
    $scope.view = '';

    $scope.detectCertificate = function() {
        $api.request({
            module: 'PineAP',
            action: 'detectEnterpriseCertificate'
        }, function(response) {
            if (response.installed === true) {
                $scope.generatingCertificate = false;
                $interval.cancel($scope.certInterval);
                $scope.view = 'normal';
            } else {
                $scope.view = 'certs';
            }
            $scope.loadingView = false;
        });
    };

    $scope.clearCertificate = function() {
        $scope.view = '';
        $scope.loadingView = true;
        $api.request({
            module: 'PineAP',
            action: 'clearEnterpriseCertificate'
        }, function() {
            $scope.view = 'certs';
            $scope.loadingView = false;
        });
    };

    $scope.clearDB = function() {
        $api.request({
            module: 'PineAP',
            action: 'clearEnterpriseDB'
        }, function() {
            $scope.getData();
        });
    };

    $scope.downloadJTR = function() {
        $api.request({
            module: 'PineAP',
            action: 'downloadJTRHashes'
        }, function(response) {
            if (response.error === undefined) {
                window.location = '/api/?download=' + response.download;
            }
        });
    };

    $scope.downloadHashcat = function() {
        $api.request({
            module: 'PineAP',
            action: 'downloadHashcatHashes'
        }, function(response) {
            if (response.error === undefined) {
                window.location = '/api/?download=' + response.download;
            }
        });
    };

    $scope.generateCertificate = function() {
        $scope.error = '';
        $scope.generatingCertificate = true;
        $scope.certInterval = $interval(function() {
            $scope.detectCertificate();
        }, 10000);

        $api.request({
            module: 'PineAP',
            action: 'generateEnterpriseCertificate',
            certSettings: $scope.certificateSettings
        }, function(response) {
            if (response.success) {
            } else {
                $scope.generatingCertificate = false;
                $scope.error = response.error;
                $timeout(function() {
                    $scope.error = '';
                }, 5000);
                $interval.cancel($scope.certInterval);
            }
        });
    };

    $scope.getSettings = function() {
        $api.request({
            module: 'PineAP',
            action: 'getEnterpriseSettings'
        }, function(response) {
            if (response.error === undefined) {
                $scope.settings = response.settings;
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.saveSettings = function() {
        $scope.error = '';
        $scope.savingSettings = true;
        $api.request({
            module: 'PineAP',
            action: 'setEnterpriseSettings',
            settings: $scope.settings
        }, function(response) {
            if (response.success) {
                $scope.savingSettings = false;
                $scope.savedSettings = true;
                $timeout(function(){
                    $scope.savedSettings = false;
                }, 2000);
            } else {
                $scope.savingSettings = false;
                $scope.error = response.error;
                $timeout(function() {
                    $scope.error = '';
                }, 5000);
            }
        });
    };

    $scope.dataInterval = $interval(function() {
        if ($scope.view === 'normal') {
            $scope.getData();
        }
    }, 5000);

    $scope.getData = function() {
        $api.request({
            module: 'PineAP',
            action: 'getEnterpriseData'
        }, function(response) {
            $scope.chalrespdata = response.chalrespdata;
            $scope.basicdata = response.basicdata;
        });
    };

    $scope.detectCertificate();
    $scope.getSettings();
    $scope.getData();

    $scope.$on('$destroy', function() {
        $interval.cancel($scope.certInterval);
        $interval.cancel($scope.dataInterval);
    });
}]);

registerController("CapturedHandshakesController", ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.handshakes = [];
    $scope.clearedAllHandshakes = false;
    $scope.deletedHandshake = false;

    $scope.getAllHandshakes = function() {
        $api.request({
            module: 'PineAP',
            action: 'getAllHandshakes'
        }, function(response) {
            $scope.handshakes = response.handshakes;
        });
    };

    $scope.downloadAllHandshakes = function() {
        $api.request({
            module: 'PineAP',
            action: 'downloadAllHandshakes'
        }, function(response) {
            if (response.error === undefined) {
                window.location = '/api/?download=' + response.download;
            }
        });
    };

    $scope.clearAllHandshakes = function() {
        $api.request({
            module: 'PineAP',
            action: 'clearAllHandshakes'
        }, function(response) {
            if (response.success) {
                $scope.getAllHandshakes();
                $scope.clearedAllHandshakes = true;
                $timeout(function() {
                    $scope.clearedAllHandshakes = false;
                }, 2000);
            }
        });
    };

    $scope.downloadHandshake = function(bssid) {
        $api.request({
            module: 'PineAP',
            action: 'downloadHandshake',
            bssid: bssid,
            type: 'pcap'
        }, function(response) {
            if(response.error === undefined) {
                window.location = '/api/?download=' + response.download;
            }
        });
    };

    $scope.deleteHandshake = function(bssid) {
        $api.request({
            module: 'PineAP',
            action: 'deleteHandshake',
            bssid: bssid
        }, function(response) {
            if (response.success) {
                $scope.getAllHandshakes();
                $scope.deletedHandshake = true;
                $timeout(function() {
                    $scope.deletedHandshake = false;
                }, 2000);
            }
        })
    };

    $scope.getAllHandshakes();
}]);

registerController("PinejectorController", ['$api', '$scope', function($api, $scope){
    $scope.injecting = false;
    $scope.payload = "";
    $scope.channel = 1;
    $scope.frameCount = 0;
    $scope.delay = 100;
    $scope.channels = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
    $scope.interval = null;
    $scope.error = null;
    $scope.command = null;
    $scope.exitCode = null;
    $scope.stdout = null;
    $scope.stderr = null;
    $scope.showDetails = false;

    $scope.toggleDetails = function() {
        $scope.showDetails = !$scope.showDetails;
    };

    $scope.injectFrames = function() {
        $scope.injecting = true;
        $api.request({
            module: 'PineAP',
            action: 'inject',
            payload: $scope.payload,
            channel: $scope.channel,
            frameCount: $scope.frameCount,
            delay: $scope.delay
        }, function(resp) {
            $scope.injecting = false;
            $scope.error = resp.error;
            $scope.command = resp.command;
            $scope.exitCode = resp.exitCode;
            $scope.stdout = resp.stdout;
            $scope.stderr = resp.stderr;
        });
    };

    $scope.fixPayload = function() {
        return $scope.payload.replace(/[\\x\-:\t ]/g, '');
    };

    $scope.checkPayload = function() {
        var hexStream = $scope.fixPayload();
        return hexStream.search(/[^a-fA-F0-9]/) === -1 && (hexStream.length % 2) === 0 && hexStream.length > 0;
    };

    $scope.checkRadiotap = function() {
        return $scope.payload.startsWith('0000');
    };
}]);
