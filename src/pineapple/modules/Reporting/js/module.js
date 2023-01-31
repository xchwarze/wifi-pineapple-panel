registerController('ReportConfigurationController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.configSaved = false;
    $scope.sdDisabled = false;
    $scope.config = {
        generateReport: false,
        storeReport: false,
        sendReport: false,
        interval: 1
    };

    $scope.saveConfiguration = (function() {
        $api.request({
            module: 'Reporting',
            action: 'setReportConfiguration',
            config: $scope.config
        }, function(response) {
            if (response.error === undefined) {
                $scope.configSaved = true;
                $timeout(function() {
                    $scope.configSaved = false;
                }, 2000);
            }
        });
    });

    $scope.getConfiguration = (function() {
        $api.request({
            module: 'Reporting',
            action: 'getReportConfiguration'
        }, function(response) {
            $scope.config = response.config;
            $scope.sdDisabled = response.sdDisabled;
        });
    });

    $scope.getConfiguration();
}]);

registerController('ReportContentController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.configSaved = false;
    $scope.config = {
        pineAPLog: false,
        clearLog: false,
        siteSurvey: false,
        client: false,
        tracking: false,
        siteSurveyDuration: 15
    };
    $scope.error = "";

    $scope.saveConfiguration = (function() {
        $api.request({
            module: 'Reporting',
            action: 'setReportContents',
            config: $scope.config
        }, function(response) {
            if (response.error === undefined) {
                $scope.configSaved = true;
                $timeout(function() {
                    $scope.configSaved = false;
                }, 2000);
            }
        });
    });

    $api.request({
        module: 'Reporting',
        action: 'getReportContents'
    }, function(response) {
        $scope.config = response.config
    });
}]);

registerController('EmailConfigurationController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.configSaved = false;
    $scope.testing = false;
    $scope.config = {
        from : "",
        to : "",
        server: "",
        port: "",
        domain: "",
        username: "",
        password: "",
        tls: true,
        starttls: true
    };

    $scope.saveConfiguration = (function() {
        $api.request({
            module: 'Reporting',
            action: 'setEmailConfiguration',
            config: $scope.config
        }, function(response) {
            if (response.error === undefined) {
                $scope.configSaved = true;
                $timeout(function() {
                    $scope.configSaved = false;
                }, 2000);
            }
        });
    });

    $scope.testConfiguration = (function() {
        $scope.saveConfiguration();

        if ($scope.config['from'] === "" || $scope.config['to'] === "" || $scope.config['server'] === "" ||
            $scope.config['port'] === "" || $scope.config['domain'] === "" || $scope.config['username'] === "") {
            $scope.error = "You have not provided a correct configuration. Please check all fields and try again.";
            $timeout(function () {
                $scope.error = "";
            }, 2000);
        } else {
            $api.request({
                module: 'Reporting',
                action: 'testReportConfiguration'
            }, function (response) {
                if (response.error === undefined) {
                    $scope.testing = true;
                    $timeout(function () {
                        $scope.testing = false;
                    }, 2000);
                }
            });
        }
    });

    $api.request({
        module: 'Reporting',
        action: 'getEmailConfiguration'
    }, function(response) {
        $scope.config = response.config;
    });
}]);
