registerController("DebugController", ['$api', '$scope', '$timeout', '$interval', function($api, $scope, $timeout, $interval){
    $scope.loading = false;
    $scope.debugStarted = false;
    $scope.output = "";
    $scope.getOutputInterval = null;

    $scope.generateDebugFile = (function(){
        $api.request({
            module: "Help",
            action: "generateDebugFile"
        }, function(response) {
            if (response.success === true) {
                $scope.loading = true;
                $scope.debugStarted = true;
                $timeout($scope.downloadDebugFile, 15000);
                if ($scope.getOutputInterval === null) {
                    $scope.getOutputInterval = $interval($scope.getOutput, 700);
                }
            }
        })
    });

    $scope.getOutput = (function() {
        $api.request({
            module: "Help",
            action: "getConsoleOutput"
        }, function(response) {
            $scope.output = response.output;
            var el = $("#output");
            if (el.length) {
                el.scrollTop(el[0].scrollHeight - el.height())
            }
        });
    });

    $scope.tryAgainSoon = (function(){
        $timeout($scope.downloadDebugFile, 2000);
    });

    $scope.downloadDebugFile = (function(){
        $api.request({
            module: "Help",
            action: "downloadDebugFile"
        }, function(response) {
            if (response.success === true) {
                $scope.loading = false;
                window.location = '/api/?download=' + response.downloadToken;
                $interval.cancel($scope.getOutputInterval);
            } else {
                $scope.tryAgainSoon();
            }
        })
    });
}]);
registerController("HelpController", ['$api', '$scope', function($api, $scope) {
    $scope.device = "";

    $api.onDeviceIdentified(function(device, scope) {
        scope.device = device;
    }, $scope);
}]);