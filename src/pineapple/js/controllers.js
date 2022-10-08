(function(){
    angular.module('pineapple')
    .controller('NavigationController', ['$scope', '$api', '$routeParams', function($scope, $api, $routeParams) {
        $scope.systemModules = [];
        $scope.userModules = [];
        $scope.selectedIndex = -1;

        $scope.getClass = function(moduleName) {
            var status = ($routeParams.moduleName === $(".sidebar-nav li[module="+ moduleName +"]").attr("module"));
            return status ? 'active' : '';
        };

        $scope.getModuleClass = function() {
            return $(".module-nav li.active").length ? 'active' : '';
        };

        $scope.getModuleList = (function () {
            $api.request({
                system: 'modules',
                action: 'getModuleList'
            }, function(data) {
                if (data.error === undefined){
                    $scope.systemModules = data.modules.systemModules;
                    $scope.userModules = data.modules.userModules;
                    $scope.processModulesExtras();
                }
            });
        });

        $scope.processModulesExtras = (function () {
            angular.forEach($scope.userModules, function(value) {
                if (value.injectJS) {
                    $('head').append( $('<script src="' + value.injectJS + '"></script>') );
                }
            });
        });

        $scope.getAlias = function(moduleName) {
            return moduleName.match(/(\b\S)?/g).join('').match(/(^\S|\S$)?/g).join('').toUpperCase();
        };

        $api.registerNavbar($scope.getModuleList);
        $scope.getModuleList();
    }])


    .controller('NotificationController', ['$scope', '$api', '$interval', function($scope, $api, $interval){
        $scope.notifications = [];

        $api.getNotifications(function(data){
            $scope.notifications = data;
        });

        $scope.clearNotifications = function(){
            $scope.notifications = [];
            $api.clearNotifications();
        };

        $scope.notificationInterval = $interval(function() {
            $api.getNotifications(function(data){
                $scope.notifications = data;
            });
        }, 30000);

        $scope.$on('$destroy', function() {
            $interval.cancel($scope.notificationInterval);
        });
    }])


    .controller('AuthenticationController', ['$scope', '$api', function($scope, $api){
        $scope.username = "root";
        $scope.password = "";
        $scope.message = "";

        $scope.login = function(){
            $api.login($scope.username, $scope.password, function(data){
                if (data.logged_in !== undefined && data.logged_in === false) {
                    $scope.message = "Invalid username or password.";
                } else {
                    window.location.reload();
                }
            });
        };

        $scope.logout = function(){
            $api.logout(function(){
                window.location.reload();
            });
        };

        $scope.haltPineapple = (function() {
            if (confirm("Are you sure you want to shutdown your WiFi Pineapple?")) {
                $api.request({
                    module: "Configuration",
                    action: "haltPineapple"
                }, function(response) {
                    if (response.success !== undefined) {
                        alert("Your WiFi Pineapple is now shutting down. Once the LED has turned off, it is safe to unplug.");
                    }
                });
            }
        });

        $scope.rebootPineapple = (function() {
            if (confirm("Are you sure you want to reboot your WiFi Pineapple?")) {
                $api.request({
                    module: "Configuration",
                    action: "rebootPineapple"
                }, function(response) {
                    if (response.success !== undefined) {
                        alert("Your WiFi Pineapple is now rebooting. You may need to reconnect once it is done.");
                    }
                });
            }
        });
    }]);
})();