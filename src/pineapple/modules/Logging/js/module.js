registerController('PineAPLogController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.log = [];
    $scope.mac = '';
    $scope.ssid = '';
    $scope.logLocation = '';
    $scope.locationModified = false;
    $scope.orderByName = 'log_time';
    $scope.reverseSort = true;

    $scope.checkboxOptions = {
        probes: true,
        associations: true,
        removeDuplicates: false
    };


    $scope.refreshLog = (function() {
        $scope.log = [];
        $api.request({
            module: 'Logging',
            action: 'getPineapLog'
        }, function(response) {
            if (response.error === undefined) {
                $scope.log = response.pineap_log;
                $scope.applyFilter();
                annotateMacs();
            }
        });
    });

    $scope.downloadLog = (function() {
        $api.request({
            module: 'Logging',
            action: 'downloadPineapLog'
        }, function(response) {
            if (response.error === undefined) {
                window.location = '/api/?download=' + response.download;
            }
        });
    });

    $scope.getPineapLogLocation = (function () {
        $api.request({
            module: 'Logging',
            action: 'getPineapLogLocation'
        }, function(response) {
            if (response.error === undefined) {
                $scope.logLocation = response.location;
            }
        });
    });

    $scope.setPineapLogLocation = (function () {
        $api.request({
            module: 'Logging',
            action: 'setPineapLogLocation',
            location: $scope.logLocation
        }, function(response) {
            if (response.error === undefined) {
                $scope.locationModified = true;
                $timeout(function() {
                    $scope.locationModified = false;
                }, 3000);
            }
        });
    });

    $scope.checkMatch = (function(text, filter) {
        if (filter.trim() === '') {
            return true;
        }
        if (text.toLowerCase().indexOf(filter.toLowerCase()) !== -1) {
            return true;
        }
        try {
            var re = new RegExp(filter);
            if (text.match(re) !== null) {
                return true;
            }
        }
        catch (err) {}
        return false;
    });

    $scope.applyFilter = (function() {
        var hashArray = [];
        $.each($scope.log, function(i, value){
            if (value.log_time !== '') {
                value.hidden = false;
                if ($scope.checkboxOptions.removeDuplicates) {
                    var index = value.ssid + value.log_type + value.mac;
                    if (hashArray[index] === undefined) {
                        hashArray[index] = true;
                    } else {
                        value.hidden = true;
                        return true;
                    }
                }

                if (!$scope.checkboxOptions.probes) {
                    if (value.log_type === 0) {
                        value.hidden = true;
                    }
                }
                if (!$scope.checkboxOptions.associations) {
                    if (value.log_type === 1 || value.log_type === 2) {
                        value.hidden = true;
                    }
                }

                if (!$scope.checkMatch(value.mac, $scope.mac)) {
                    value.hidden = true;
                } else if (!$scope.checkMatch(value.ssid, $scope.ssid)) {
                    value.hidden = true;
                }
            }
        });
    });

    $scope.clearFilter = (function() {
        $scope.mac = '';
        $scope.ssid = '';
        $scope.checkboxOptions.probes = true;
        $scope.checkboxOptions.associations = true;

        $scope.applyFilter();
    });

    $scope.clearLog = (function() {
        $api.request({
            module: 'Logging',
            action: 'clearPineapLog'
        }, function(response) {
            if (response.error === undefined) {
                $scope.log = [];
            }
        });
    });

    $scope.getPineapLogLocation();
    $scope.refreshLog();
}]);

registerController('SyslogController', ['$api', '$scope', function($api, $scope) {
    $scope.syslog = 'Loading..';

    $scope.refreshLog = (function() {
        $api.request({
            module: 'Logging',
            action: 'getSyslog'
        }, function(response) {
            if (response.error === undefined) {
                $scope.syslog = response;
            }
        })
    });

    $scope.refreshLog();
}]);

registerController('DmesgController', ['$api', '$scope', function($api, $scope) {
    $scope.dmesg = 'Loading..';

    $scope.refreshLog = (function() {
        $api.request({
            module: 'Logging',
            action: 'getDmesg'
        }, function(response) {
            if (response.error === undefined) {
                $scope.dmesg = response;
            }
        })
    });

    $scope.refreshLog();
}]);

registerController('ReportingLogController', ['$api', '$scope', function($api, $scope) {
    $scope.reportingLog = "";

    $scope.refreshLog = (function() {
        $api.request({
            module: 'Logging',
            action: 'getReportingLog'
        }, function(response) {
            if (response.error === undefined) {
                $scope.reportingLog = response;
            }
        })
    });

    $scope.refreshLog();
}]);
