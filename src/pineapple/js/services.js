(function(){
    angular.module('pineapple')
    .service('$api', ['$http', function($http){
        this.navbarReloader = false;
        this.device = undefined;
        this.deviceCallbacks = [];

        this.request = (function(data, callback, scope) {

            return $http.post('/api/', data).
            then(function(response){
                if (response.data.error === "Not Authenticated") {
                    if (response.data.setupRequired === true) {
                        if (window.location.hash !== "#!/modules/Setup") {
                            window.location.hash = "#!/modules/Setup";
                        }
                    } else {
                        $("#loginModal").modal({
                            show: true,
                            keyboard: false,
                            backdrop: 'static'
                        });
                    }
                    $(".logout").hide();
                }
                if (callback !== undefined) {
                    if (scope !== undefined) {
                        callback(response.data, scope);
                    } else {
                        callback(response.data);
                    }
                }
            }, function(response) {
                callback({error: 'HTTP Error', HTTPError: response.statusText, HTTPCode: response.status});
            });
        });

        this.login = (function(user, pass, callback){
            return this.request({system: 'authentication', action: 'login', username: user, password: pass, time: Math.floor((new Date).getTime()/1000)}, function(data){
                callback(data);
            }, this);
        });
        this.logout = (function(callback){
            return this.request({system: 'authentication', action: 'logout'}, callback);
        });


        this.registerNavbar = (function(reloader) {
            this.navbarReloader = reloader;
        });

        this.reloadNavbar = (function() {
            this.navbarReloader();
        });


        this.checkAuth = (function(callback){
            return this.request({system: 'authentication', action: 'checkAuth'}, function(data){
                if (callback !== undefined) {
                    callback(data);
                }
            });
        });

        this.getNotifications = function(callback){
            this.request({
                system: 'notifications',
                action: 'listNotifications'
            }, function(data) {
                callback(data);
            });
        };
        this.clearNotifications = function(){
            this.request({
                system: 'notifications',
                action: 'clearNotifications'
            });
        };
        this.addNotification = function(notificationMessage){
            this.request({
                system: 'notifications',
                action: 'addNotification',
                message: notificationMessage
            });
        };

        this.onDeviceIdentified = function(callback, scope){
            this.deviceCallbacks.push({callback: callback, scope: scope});
            if (this.device !== undefined) {
                for (var i = this.deviceCallbacks.length-1; i >=0; --i) {
                    this.deviceCallbacks[i].callback(this.device, this.deviceCallbacks[i].scope);
                }
            }
        };

        this.request({
            module: 'Configuration',
            action: 'getDevice'
        }, function(response, scope){
            scope.device = response.device;
            for (var i = scope.deviceCallbacks.length-1; i >=0; --i) {
                var callbackObj = scope.deviceCallbacks[i];
                callbackObj.callback(scope.device, callbackObj.scope);
            }
        }, this);

        this.checkAuth();
    }]);
})();