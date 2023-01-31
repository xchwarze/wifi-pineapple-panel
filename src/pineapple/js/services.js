(function(){
    angular.module('pineapple')

    .config(function ($provide, $httpProvider) {
        $httpProvider.interceptors.push(['$injector', '$q', function interceptors($injector, $q) {
            return {
                request: function request(config) {
                    var $http = $injector.get('$http');
                    var _config = angular.copy(config);
                    delete _config.headers;

                    function isConfigEqual(pendingRequestConfig) {
                        var _pendingRequestConfig = angular.copy(pendingRequestConfig);
                        delete _pendingRequestConfig.headers;
                        return angular.equals(_config, _pendingRequestConfig);
                    }

                    if ($http.pendingRequests.some(isConfigEqual)) {
                        console.log('[DUPLICATE]', config.data);
                        return $q.reject(request);
                    }

                    return config;
                }
            };
        }]);
    })

    .service('$api', ['$http', function($http){
        this.navbarReloader = false;
        this.device = undefined;
        this.deviceConfig = undefined;
        this.deviceCallbacks = [];

        this.request = (function(data, callback, scope) {
            return $http.post('/api/', data, { timeout: 30000 }).
            then(function(response){
                if (response.data.error === 'Not Authenticated') {
                    if (response.data.setupRequired === true) {
                        if (window.location.hash !== '#!/modules/Setup') {
                            window.location.hash = '#!/modules/Setup';
                        }
                    } else {
                        $('#loginModal').modal({
                            show: true,
                            keyboard: false,
                            backdrop: 'static'
                        });
                    }
                    $('.logout').hide();
                }
                if (callback !== undefined) {
                    if (scope !== undefined) {
                        callback(response.data, scope);
                    } else {
                        callback(response.data);
                    }
                }
            }, function(response) {     
                callback(
                    (response.statusText || response.status) ?
                        { error: 'HTTP Error', HTTPError: response.statusText, HTTPCode: response.status } : {}
                );
            });
        });

        this.login = function(user, pass, callback){
            return this.request({system: 'authentication', action: 'login', username: user, password: pass, time: Math.floor((new Date).getTime()/1000)}, function(data){
                callback(data);
            }, this);
        };

        this.logout = function(callback){
            return this.request({system: 'authentication', action: 'logout'}, callback);
        };

        this.registerNavbar = function(reloader) {
            this.navbarReloader = reloader;
        };

        this.reloadNavbar = function() {
            this.navbarReloader();
        };

        this.checkAuth = function(callback){
            return this.request({system: 'authentication', action: 'checkAuth'}, function(data){
                if (callback !== undefined) {
                    callback(data);
                }
            });
        };

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
            if (callback) {
                this.deviceCallbacks.push({callback: callback, scope: scope});
            }

            if (this.device !== undefined) {
                for (var i = this.deviceCallbacks.length-1; i >=0; --i) {
                    this.deviceCallbacks[i].callback(this.device, this.deviceCallbacks[i].scope);
                }
                this.deviceCallbacks = [];
            }
        };

        this.ouiPresent = function() {
            return localStorage.getItem('ouiText') !== null;
        };

        this.loadOUIFile = function(callback) {
            $http.get('https://raw.githubusercontent.com/xchwarze/wifi-pineapple-community/main/oui/oui.txt').then(
                function(response) {
                    window.pineapple.populateDB(response.data, callback);
                },
                function() {
                    $api.request({
                        module: 'Networking',
                        action: 'getOUI'
                    }, function(response) {
                        if (response.error === undefined) {
                            window.pineapple.populateDB(response.ouiText, callback);
                        }
                    }
                );
            });
        };

        this.populateDB = function(text, callback) {
            var request = window.indexedDB.open('pineapple', 1);

            request.onsuccess = function() {
                localStorage.setItem('ouiText', true);
                if (callback) {
                    callback();
                }
            };

            request.onerror = function(event) {
                console.error(event);
                console.error('Database error: ' + event.target.error.message);
            };

            request.onupgradeneeded = function(event) {
                var db = event.target.result;
                var objectStore = db.createObjectStore('oui', { keyPath: 'macPrefix' });
                var pos = 0;
                var totalLines = 0;
                do {
                    var line = text.substring(pos, text.indexOf('\n', pos + 1)).replace('\n', '');

                    objectStore.add({
                        macPrefix: line.substring(0, 6),
                        name: line.substring(6)
                    });
                    
                    pos += line.length + 1;
                    totalLines++;
                } while (text.indexOf('\n', pos + 1) !== -1);
            };
        };

        this.deleteOUI = function(callback) {
            localStorage.removeItem('ouiText');
            window.indexedDB.deleteDatabase('pineapple').onsuccess = function() {
                if (callback) {
                    callback();
                }
            };
        };

        this.lookupOUI = function(mac, callback) {
            var request = window.indexedDB.open('pineapple', 1);
            request.onsuccess = function() {
                var db = request.result;
                var transaction = db.transaction(['oui'], 'readwrite');
                transaction.onerror = function() {
                    callback('Error retrieving OUI. Please clear your browser cache.');
                };
                
                var prefix = mac.substring(0, 8).replace(/:/g,'');
                var lookupReq = transaction.objectStore('oui').get(prefix);
                lookupReq.onerror = function() {
                    window.indexedDB.deleteDatabase('pineapple');
                    callback('Error retrieving OUI');
                };
                
                lookupReq.onsuccess = function() {
                    callback(lookupReq.result ? lookupReq.result.name : 'Unknown MAC prefix');
                };
            }
        };

        this.request({
            module: 'Configuration',
            action: 'getDeviceConfig'
        }, function(response, scope){
            if (!response || response.error) {
                return;
            }

            scope.device = response.config.deviceType;
            scope.deviceConfig = response.config;
            scope.onDeviceIdentified();
        }, this);

        this.checkAuth();
    }]);
})();