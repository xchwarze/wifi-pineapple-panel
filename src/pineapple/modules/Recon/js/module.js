registerController('ReconController', ['$api', '$scope', '$rootScope', '$interval', '$timeout', '$cookies', function($api, $scope, $rootScope, $interval, $timeout, $cookies) {
    $scope.accessPoints = [];
    $scope.unassociatedClients = [];
    $scope.outOfRangeClients = [];
    $scope.scans = [];
    $scope.selectedScan = "";
    $scope.loadedScan = null;
    $scope.orderByName = 'ssid';
    $scope.wsAuthToken = "";
    /*
        This needs to be an object as it is not supported to ng-bind primitive types.
        Eventually we should update all of the primitive values we ng-bind
        References:
            - http://www.codelord.net/2014/05/10/understanding-angulars-magic-dont-bind-to-primitives/
            - https://stackoverflow.com/questions/38078884/two-way-binding-on-primitive-variables-in-angularjs-directive
     */
    $scope.scanSettings = {
        scanDuration: $cookies.get('scanDuration') !== undefined ? $cookies.get('scanDuration') : '0',
        live: $cookies.get('liveScan') !== undefined ? $cookies.get('liveScan') === 'true' : true
    };
    $scope.scanType = '0';
    $scope.updateInterval = 1500;
    $scope.percentageInterval = 300;
    $scope.percent = 0;
    $scope.preparingScan = false;
    $scope.running = false;
    $scope.pineAPDRunning = true;
    $scope.pineAPDStarting = false;
    $scope.paused = false;
    $scope.reverseSort = false;
    $scope.loading = false;
    $scope.loadingScan = false;
    $scope.error = false;
    $scope.scanID = null;
    $scope.device = undefined;
    $scope.wsStarted = false;
    $scope.noteKeys = [];
    $scope.noteRefreshInterval = null;
    $scope.statusObtained = false;
    $rootScope.captureRunning = false;

    $scope.shouldShowScan = function(scan, scanid) {
        return $scope.scanID !== scanid || !$scope.running;
    };

    $scope.updateScanSettings = function() {
        $cookies.put('scanDuration', $scope.scanSettings.scanDuration);
        if ($scope.scanSettings.scanDuration === "0") {
            $scope.scanSettings.live = true;
        }
        $cookies.put('liveScan', $scope.scanSettings.live);($cookies.getAll());
    };

    function parseScanResults(results) {
        annotateMacs();
        var data = results['results'];
        $scope.accessPoints = data['ap_list'];
        $scope.unassociatedClients = data['unassociated_clients'];
        $scope.outOfRangeClients = data['out_of_range_clients'];
    }

    function checkScanStatus() {
        if ($scope.scanSettings.scanDuration < 1) {
            return;
        }
        if (!$scope.updatePercentageInterval) {
            $scope.updatePercentageInterval = $interval(function () {
                var percentage = $scope.percentageInterval / ($scope.scanSettings.scanDuration * 10);
                if (($scope.percent + percentage) >= 100 && $scope.running && !$scope.loading) {
                    $scope.percent = 100;
                    $scope.checkScan();
                } else if ($scope.percent + percentage < 100 && $scope.running) {
                    $scope.percent += percentage;
                }
            }, $scope.percentageInterval);
        }
    }

    $scope.checkScan = function() {
        $api.request({
            module: 'Recon',
            action: 'checkScanStatus',
            scanID: $scope.scanID
        }, function (response) {
            $rootScope.captureRunning = response.captureRunning;
            $scope.percent = response.scanPercent;
            if (response.completed === true) {
                if (!$scope.running && !$scope.loading) {
                    $scope.percent = 100;
                }
                if ($scope.running) {
                    $scope.stopScan();
                    $scope.scans = $scope.scans || [];
                    $scope.selectedScan = $scope.scans[$scope.scans.length - 1];
                    $scope.displayScan();
                }
            } else if (response.completed === false) {
                if (response.scanID !== null && response.scanID !== undefined) {
                    $scope.scanID = response.scanID;
                }
            }
        });
    };

    $scope.startPineAP = function() {
        $scope.pineAPDStarting = true;
        $api.request({
            module: 'Recon',
            action: 'startPineAPDaemon'
        }, function(response){
            $scope.pineAPDStarting = false;
            if (response.error === undefined) {
                $scope.pineAPDRunning = true;
                $scope.startScan();
                $scope.error = null;
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.startScan = function() {
        $scope.preparingScan = true;
        $scope.percent = 0;
        if ($scope.running) {
            return;
        }
        if ($scope.scanSettings.scanDuration === "0") {
            $scope.scanSettings.live = true;
        }
        if ($scope.scanSettings.live === true) {
            $scope.startLiveScan();
        } else {
            $scope.startNormalScan();
        }
        $scope.accessPoints = [];
        $scope.unassociatedClients = [];
        $scope.outOfRangeClients = [];
        checkScanStatus();
    };

    $scope.startLiveScan = function() {
        $scope.loading = true;

        $api.request({
            module: 'Recon',
            action: 'startLiveScan',
            scanType: $scope.scanType,
            scanDuration: $scope.scanSettings.scanDuration
        }, function(response) {
            if (response.success) {
                $scope.loading = false;
                $scope.preparingScan = false;
                $scope.running = true;
                $scope.scanID = response.scanID;
                if ($scope.wsStarted !== true) {
                    $scope.startWS();
                }
            } else {
                if (response.error === "The PineAP Daemon must be running.") {
                    $scope.pineAPDRunning = false;
                }
                $scope.error = response.error;
            }
        });
    };

    $scope.startNormalScan = function() {
        if ($scope.running) {
            return;
        }

        $scope.loading = true;

        $api.request({
            module: 'Recon',
            action: 'startNormalScan',
            scanType: $scope.scanType,
            scanDuration: $scope.scanSettings.scanDuration
        }, function(response) {
            if (response.success) {
                $scope.loading = false;
                $scope.preparingScan = false;
                $scope.running = true;
                $scope.scanID = response.scanID;
            } else {
                if (response.error === "The PineAP Daemon must be running.") {
                    $scope.pineAPDRunning = false;
                }
                $scope.error = response.error;
            }
        });
    };

    $scope.cancelIntervals = function() {
        if ($scope.checkScanInterval) {
            $interval.cancel($scope.checkScanInterval);
        }
        if ($scope.updatePercentageInterval) {
            $interval.cancel($scope.updatePercentageInterval);
        }
        if ($scope.noteRefreshInterval) {
            $interval.cancel($scope.noteRefreshInterval);
        }
        if ($scope.wsTimeout) {
            $timeout.cancel($scope.wsTimeout);
        }
        $scope.checkScanInterval = null;
        $scope.updatePercentageInterval = null;
        $scope.noteRefreshInterval = null;
        $scope.wsTimeout = null;
    };

    $scope.stopScan = function() {
        $scope.getScans();
        $scope.percent = 0;
        $scope.paused = false;
        $scope.running = false;

        $api.request({
            module: 'Recon',
            action: 'stopScan'
        }, function(response) {
            if (response.success === true) {
                $scope.running = false;
                $scope.closeWS();
            }
        });
    };

    $scope.pauseLiveScan = function() {
        $scope.paused = true;
    };

    $scope.resumeLiveScan = function() {
        $scope.paused = false;
    };

    $scope.stopHandshake = function() {
        $api.request({
            module: 'PineAP',
            action: 'stopHandshakeCapture'
        }, function(response) {
            if (response.success) {
                $rootScope.captureRunning = false;
            }
        });
    };

    $scope.convertDateToBrowserTime = function(scanDate) {
        var m = [
            "01", "02", "03",
            "04", "05", "06",
            "07", "08", "09",
            "10", "11", "12"
        ];

        var ts = scanDate.replace(' ', 'T');
        ts += 'Z';

        var d = new Date(ts);
        var day = `${d.getDate()}`.padStart(2, '0');
        var year = d.getFullYear();
        var month = d.getMonth();
        var hour = `${d.getHours()}`.padStart(2, '0');
        var mins = `${d.getMinutes()}`.padStart(2, '0');
        var secs = `${d.getSeconds()}`.padStart(2, '0');

        return year + '-' + m[month] + '-' + day + ' ' + hour + ':' + mins + ':' + secs;
    };

    $scope.getScans = function() {
        $api.request({
            module: 'Recon',
            action: 'getScans'
        }, function(response) {
            if(response.error === undefined) {
                $scope.scans = response.scans;
                $scope.scans.forEach((scan) => {
                    scan.date = $scope.convertDateToBrowserTime(scan.date);
                });
                $scope.selectedScan = response.scans[0];
                $scope.statusObtained = true;
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.setScanLocation = function() {
        $api.request({
            module: 'Recon',
            action: 'setScanLocation',
            scanLocation: $scope.scanLocation
        }, function(response) {
            if (response.success) {
                $scope.getScanLocation();
                $scope.setLocationSuccess = true;
                $timeout(function () {
                    $scope.setLocationSuccess = false;
                }, 2000);
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.getScanLocation = function() {
        $api.request({
            module: 'Recon',
            action: 'getScanLocation'
        }, function(response) {
            if (response.error === undefined) {
                $scope.scanLocation = response.scanLocation;
                $scope.getScans();
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.displayScan = function() {
        $scope.getNoteKeys();
        $scope.loadingScan = true;
        $api.request({
            module: 'Recon',
            action: 'getScans'
        }, function(response) {
            if(response.error === undefined) {
                $scope.scans = response.scans;
                $scope.scans.forEach((scan) => {
                    scan.date = $scope.convertDateToBrowserTime(scan.date);
                });
                $api.request({
                    module: 'Recon',
                    action: 'loadResults',
                    scanID: $scope.selectedScan['scan_id']
                }, function(response) {
                    parseScanResults(response);
                    $scope.loadingScan = false;
                    $scope.loadedScan = $scope.selectedScan;
                    $scope.scanID = $scope.selectedScan['scan_id'];
                });
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.closeWS = (function() {
        if ($scope.ws !== undefined) {
            $scope.ws.close();
            $scope.wsStarted = false;
        }
    });

    $scope.doWS = (function() {
        if ($scope.ws !== undefined && $scope.ws.readyState !== WebSocket.CLOSED) {
            return;
        }
        $scope.ws = new WebSocket("ws://" + window.location.hostname + ":1337/?authtoken=" + $scope.wsAuthToken);
        $scope.ws.onerror = (function() {
            $scope.wsTimeout = $timeout($scope.startWS, 1000);
        });
        $scope.ws.onopen = (function() {
            $scope.ws.onerror = (function(){});
            $scope.running = true;

        });
        $scope.ws.onclose = (function() {
            $scope.listening = false;
            $scope.closeWS();
        });

        $scope.ws.onmessage = (function(message) {
            $scope.listening = true;
            if ($scope.paused) {
                return;
            }
            var data = JSON.parse(message.data);
            if (data.scan_complete === true) {
                $scope.checkScan();
                return;
            }
            $scope.accessPoints = data.ap_list;
            $scope.unassociatedClients = data.unassociated_clients;
            $scope.outOfRangeClients = data.out_of_range_clients;
            annotateMacs();
        });
    });

    $scope.startWS = (function() {
        $scope.wsStarted = true;
        $api.request({
            module: 'Recon',
            action: 'getWSAuthToken'
        }, function(response) {
            if (response.success === true) {
                $scope.wsAuthToken = response.wsAuthToken;
                $scope.doWS();
            } else {
                $scope.wsTimeout = $timeout($scope.startWS, 1500);
            }
        });
    });

    $scope.addAllSSIDS = function() {
        var ssidList = [];
        if ($scope.accessPoints.length !== 0) {
            angular.forEach($scope.accessPoints, function(value) {
                if (value.ssid !== "") {
                    ssidList.push(value.ssid);
                }
            });
            $api.request({
                module: "PineAP",
                action: "addSSIDs",
                ssids: ssidList
            }, function(response) {
                if (response.success) {
                    $scope.dropdownMessage = "All SSIDs added to Pool.";
                    $scope.wsTimeout = $timeout(function() {
                        $scope.dropdownMessage = "";
                    }, 3000);
                }
            });
        }
    };

    $scope.downloadResults = function() {
        $scope.getScans();
        $api.request({
            module: 'Recon',
            action: 'downloadResults',
            scanID: $scope.scanID
        }, function(response) {
            annotateMacs();
            if (response.error === undefined) {
                window.location = '/api?download=' + response.download;
            }
        });
    };

    $scope.removeScan = function() {
        $api.request({
            module: 'Recon',
            action: 'removeScan',
            scanID: $scope.selectedScan['scan_id']
        }, function(response) {
            if(response.error === undefined) {
                $scope.removedScan = true;
                $scope.loadedScan = null;
                $scope.accessPoints = [];
                $scope.unassociatedClients = [];
                $scope.outOfRangeClients = [];
                $timeout(function() {
                    $scope.removedScan = false;
                }, 2000);
                $scope.getScans();
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.displayCurrentScan = function() {
        $api.request({
            module: 'Recon',
            action: 'checkScanStatus'
        }, function(response) {
            $scope.getScans();
            if (!response.completed && response.scanID !== null) {
                $scope.scanID = response.scanID;
                $scope.loading = true;
                if (response.continuous) {
                    $scope.scanSettings.scanDuration = "0";
                    $scope.scanSettings.live = true;
                    $scope.percent = response.scanPercent;
                }
                $api.request({
                    module: 'Recon',
                    action: 'startReconPP'
                }, function() {
                    if ($scope.wsStarted !== true) {
                        $scope.startWS();
                    }
                    $scope.running = true;
                    checkScanStatus();
                    $scope.loading = false;
                });
            }
        });
    };

    $scope.getNoteKeys = function() {
        $api.request({
            module: "Notes",
            action: "getKeys"
        }, function(response) {
            $scope.noteKeys = response.keys;
        });
    };

    $scope.hasNote = function(key) {
        return ($scope.noteKeys !== undefined) && ($scope.noteKeys.indexOf(key) !== -1);
    };


    $scope.checkScan();
    $scope.$on('$destroy', function() {
        $scope.cancelIntervals();
        $scope.closeWS();
    });

    $api.onDeviceIdentified(function(device) {
        $scope.updateScanSettings();
        $scope.device = device;
        $scope.getScanLocation();
        $scope.displayCurrentScan();
        $scope.getNoteKeys();
    }, $scope);
}]);
