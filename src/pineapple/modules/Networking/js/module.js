registerController('NetworkingRouteController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.restartedDNS = false;
    $scope.routeTable = "";
    $scope.routeInterface = "br-lan";
    $scope.routeInterfaces = [];


    $scope.getRoute = (function(){
        $api.request({
            module: 'Networking',
            action: 'getRoutingTable'
        }, function(response){
            $scope.routeTable = response.routeTable;
            $scope.routeInterfaces = response.routeInterfaces;
        });
    });

    $scope.restartDNS = (function() {
        $api.request({
            module: 'Networking',
            action: 'restartDNS'
        }, function(response) {
            if (response.success === true) {
                $scope.restartedDNS = true;
                $timeout(function(){
                    $scope.restartedDNS = false;
                }, 2000);
            }
        });
    });

    $scope.updateRoute = (function() {
        $api.request({
            module: 'Networking',
            action: 'updateRoute',
            routeIP: $scope.routeIP,
            routeInterface: $scope.routeInterface
        }, function(response) {
            if (response.success === true) {
                $scope.getRoute();
                $scope.updatedRoute = true;
                $timeout(function(){
                    $scope.updatedRoute = false;
                }, 2000);
            }
        });
    });

    $scope.getRoute();

}]);

registerController('NetworkingAccessPointsController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.apConfigurationSaved = false;
    $scope.apConfigurationError = "";
    $scope.apAvailableChannels = [];
    $scope.apConfig = {
        availableChannels: [],
        selectedChannel: "1",
        openSSID: "",
        hideOpenAP: false,
        managementSSID: "",
        managementKey: "",
        disableManagementAP: false,
        hideManagementAP: false
    };

    $scope.saveAPConfiguration = (function() {
        $api.request({
            module: "Networking",
            action: "saveAPConfig",
            apConfig: $scope.apConfig
        }, function(response) {
            if (response.error === undefined) {
                $scope.apConfigurationSaved = true;
                $timeout(function(){
                    $scope.apConfigurationSaved = false;
                }, 6000);
            } else {
                $scope.apConfigurationError = response.error;
                $timeout(function(){
                    $scope.apConfigurationError = "";
                }, 3000);
            }
        })
    });

    $scope.getAPConfiguration = (function() {
        $api.request({
            module: "Networking",
            action: "getAPConfig"
        }, function(response) {
            if (response.error === undefined) {
                $scope.apConfig = response;
                if ($scope.apConfig['selectedChannel'] === true) {
                    $scope.apConfig['selectedChannel'] = "1";
                }
            }
        })
    });

    $scope.getAPConfiguration();
}]);

registerController('NetworkingClientModeController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.interfaces = [];
    $scope.selectedInterface = "";
    $scope.accessPoints = [];
    $scope.selectedAP = {};
    $scope.scanning = false;
    $scope.key = "";
    $scope.connected = true;
    $scope.connecting = false;
    $scope.noNetworkFound = false;

    $scope.getInterfaces = (function() {
        $api.request({
            module: 'Networking',
            action: 'getClientInterfaces'
        }, function(response) {
            if (response.error === undefined) {
                $scope.interfaces = response;
                $scope.selectedInterface = $scope.interfaces[0];
            }
        });
    });

    $scope.scanForNetworks = (function() {
        $scope.scanning = true;
        $api.request({
            module: 'Networking',
            action: 'scanForNetworks',
            interface: $scope.selectedInterface
        }, function(response) {
            if (response.error !== undefined) {
                $scope.noNetworkFound = true;
            } else {
                $scope.noNetworkFound = false;
                $scope.accessPoints = response;
                $scope.selectedAP = $scope.accessPoints[0];
            }
            $scope.scanning = false;
        });
    });

    $scope.connectToAP = (function() {
        $scope.connecting = true;
        $api.request({
            module: 'Networking',
            action: 'connectToAP',
            interface: $scope.selectedInterface,
            ap: $scope.selectedAP,
            key: $scope.key
        }, function() {
            $scope.key = "";
            $timeout(function() {
                $scope.checkConnection();
                $scope.connecting = false;
            }, 10000);
        });
    });

    $scope.checkConnection = (function() {
        $api.request({
            module: 'Networking',
            action: 'checkConnection'
        }, function(response) {
            if (response.error === undefined) {
                if (response.connected) {
                    $scope.connected = true;
                    $scope.connectedInterface = response.interface;
                    $scope.connectedSSID = response.ssid;
                    $scope.connectedIP = response.ip;
                } else {
                    $scope.connected = false;
                    $scope.getInterfaces();
                }
            }
        });
    });

    $scope.disconnect = (function() {
        $scope.disconnecting = true;
        $api.request({
            module: 'Networking',
            action: 'disconnect',
            interface: $scope.connectedInterface
        }, function(response) {
            if (response.error === undefined) {
                $timeout(function() {
                    $scope.getInterfaces();
                    $scope.connected = false;
                    $scope.disconnecting = false;
                    $scope.accessPoints = [];
                }, 10000);
            }
        });
    });

    $scope.checkConnection();
}]);

registerController('NetworkingFirewallController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.firewallUpdated = false;
    $scope.WANSSHAccess = false;
    $scope.WANUIAccess = false;
    $scope.device = '';

    $scope.getDevice = (function() {
        $api.request({
            module: 'Configuration',
            action: 'getDevice'
        }, function(response) {
            $scope.device = response.device;
        });
    });
    $scope.getDevice();

    $scope.getFirewallConfig = (function() {
        $api.request({
            module: 'Networking',
            action: 'getFirewallConfig'
        }, function(response) {
            if (response.error === undefined) {
                $scope.WANSSHAccess = response.allowWANSSH;
                $scope.WANUIAccess = response.allowWANUI;
            }
        });
    });

    $scope.setFirewallConfig = (function() {
        $api.request({
            module: 'Networking',
            action: 'setFirewallConfig',
            WANSSHAccess: $scope.WANSSHAccess,
            WANUIAccess: $scope.WANUIAccess
        }, function(response) {
            if (response.error === undefined) {
                $scope.firewallUpdated = true;
                $timeout(function(){
                    $scope.firewallUpdated = false;
                }, 2000);
            }
        })
    });

    $scope.getFirewallConfig();
}]);

registerController('NetworkingMACAddressesController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.interfaces = [];
    $scope.selectedInterface = "wlan0";
    $scope.newMac = "";
    $scope.modifyingMAC = false;

    $scope.getMacData = (function() {
        $api.request({
            module: 'Networking',
            action: 'getMacData'
        }, function(response) {
            if (response.error === undefined) {
                $scope.interfaces = response;
            }
        });
    });

    $scope.setMac = (function() {
        $scope.modifyingMAC = true;
        $api.request({
            module: 'Networking',
            action: 'setMac',
            interface: $scope.selectedInterface,
            mac: $scope.newMac
        }, function(response) {
            if (response.error === undefined) {
                $scope.newMac = "";
                $timeout(function(){
                    $scope.modifyingMAC = false;
                    $scope.getMacData();
                }, 6000);
            }
        });
    });

    $scope.setRandomMac = (function() {
        $scope.modifyingMAC = true;
        $api.request({
            module: 'Networking',
            action: 'setRandomMac',
            interface: $scope.selectedInterface
        }, function(response) {
            if (response.error === undefined) {
                $scope.newMac = "";
                $timeout(function(){
                    $scope.modifyingMAC = false;
                    $scope.getMacData();
                }, 6000);
            }
        });
    });

    $scope.resetMac = (function() {
        $scope.modifyingMAC = true;
        $api.request({
            module: 'Networking',
            action: 'resetMac',
            interface: $scope.selectedInterface
        }, function(response) {
            if (response.error === undefined) {
                $scope.newMac = "";
                $timeout(function(){
                    $scope.modifyingMAC = false;
                    $scope.getMacData();
                }, 6000);
            }
        });
    });

    $scope.getMacData();
}]);

registerController('NetworkingAdvancedController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.hostnameUpdated = false;
    $scope.wirelessReset = false;
    $scope.data = {
        hostname: "Pineapple",
        ifconfig: ""
    };

    $scope.reloadData = (function() {
        $api.request({
            module: 'Networking',
            action: 'getAdvancedData'
        }, function(response) {
            if (response.error === undefined) {
                $scope.data = response;
            }
        });
    });

    $scope.setHostname = (function() {
        $api.request({
            module: "Networking",
            action: "setHostname",
            hostname: $scope.data['hostname']
        }, function(response) {
            if (response.error === undefined) {
                $scope.hostnameUpdated = true;
                $timeout(function(){
                    $scope.hostnameUpdated = false;
                }, 2000);
            }
        });
    });

    $scope.resetWirelessConfig = (function() {
        $api.request({
            module: 'Networking',
            action: 'resetWirelessConfig'
        }, function(response) {
            if (response.error === undefined) {
                $scope.wirelessReset = true;
                $timeout(function(){
                    $scope.wirelessReset = false;
                }, 5000);
            }
        });
    });

    $scope.reloadData();
}]);

registerController("OUILookupController", ['$api', '$scope', '$timeout', '$http', function($api, $scope, $timeout, $http) {
    $scope.macAddress = "";
    $scope.vendor = "";
    $scope.OUIDBPresent = false;

    $scope.isOUIPresent = function () {
        return localStorage.getItem("ouiText") !== null;
    };

    $scope.downloadOUIDatabase = function () {
        if (typeof(Storage) === "undefined") {
            return false;
        }
        var ouiText = localStorage.getItem("ouiText");
        if (ouiText === null) {
            $scope.gettingOUI = true;
            $http.get('https://www.wifipineapple.com/oui.txt').then(
                function (response) {
                    localStorage.setItem("ouiText", response.data);
                    $scope.populateDB();
                },
                function () {
                    $api.request({
                        module: "Networking",
                        action: "getOUI"
                    }, function (response) {
                        if (response.error === undefined) {
                            localStorage.setItem("ouiText", response.ouiText);
                            $scope.populateDB();
                        } else {
                            return false;
                        }
                    });
                });
        }
        return true;
    };

    $scope.populateDB = function () {
        $scope.ouiLoading = true;
        var request = window.indexedDB.open("pineapple", 1);

        request.onupgradeneeded = function (event) {
            var db = event.target.result;
            var objectStore = db.createObjectStore("oui", {keyPath: "macPrefix"});
            var text = localStorage.getItem("ouiText");
            var pos = 0;
            do {
                var line = text.substring(pos, text.indexOf("\n", pos + 1)).replace('\n', '');
                var arr = [line.substring(0, 6), line.substring(6)];
                objectStore.add({
                    macPrefix: arr[0],
                    name: arr[1]
                });
                pos += line.length + 1;
            } while (text.indexOf("\n", pos + 1) !== -1);
        };
        $scope.ouiLoading = false;
    };

    $scope.lookupMACAddress = function() {
        $scope.ouiLoading = true;
        if (!$scope.isOUIPresent()) {
            return;
        }
        var request = window.indexedDB.open("pineapple", 1);
        request.onsuccess = function() {
            var db = request.result;
            var mac = convertMACAddress($scope.macAddress);
            var prefix = mac.substring(0, 8).replace(/:/g, '');
            var transaction = db.transaction("oui");
            var objectStore = transaction.objectStore("oui");
            var lookupReq = objectStore.get(prefix);
            lookupReq.onerror = function () {
                window.indexedDB.deleteDatabase("pineapple");
                $scope.vendor = "Error retrieving OUI";
            };
            lookupReq.onsuccess = function () {
                if (lookupReq.result) {
                    $scope.vendor = lookupReq.result.name;
                } else {
                    $scope.vendor = "Unknown MAC prefix";
                }
            };
            $scope.ouiLoading = false;
        }
    };

    $scope.removeOUIDatabase = function() {
        localStorage.removeItem('ouiText');
        window.indexedDB.deleteDatabase('pineapple').onsuccess = function() {
            $scope.success = true;
            $scope.ouiLoading = false;
            $scope.gettingOUI = false;
            $timeout(function() {
                $scope.success = false;
            }, 2000);
        };
    };

}]);