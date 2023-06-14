registerController('NetworkingRouteController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.restartedDNS = false;
    $scope.routeTable = "";
    $scope.routeInterface = "br-lan";
    $scope.routeInterfaces = [];


    $scope.reloadData = (function(){
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
                $scope.reloadData();
                $scope.updatedRoute = true;
                $timeout(function(){
                    $scope.updatedRoute = false;
                }, 2000);
            }
        });
    });

    //$scope.reloadData();
}]);

registerController('NetworkingAccessPointsController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.loading = false;
    $scope.apConfigurationSaved = false;
    $scope.apConfigurationError = "";
    $scope.apAvailableChannels = [];
    $scope.apConfig = {
        availableChannels: [],
        selectedChannel: "1",
        openSSID: "",
        hideOpenAP: false,
        disableOpenAP: false,
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

    $scope.reloadData = (function() {
        $scope.loading = true;
        $api.request({
            module: "Networking",
            action: "getAPConfig"
        }, function(response) {
            if (response.error === undefined) {
                $scope.loading = false;
                $scope.apConfig = response;
                if ($scope.apConfig['selectedChannel'] === true) {
                    $scope.apConfig['selectedChannel'] = "1";
                }
            }
        })
    });

    //$scope.reloadData();
}]);

registerController('NetworkingModeController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.interfaces = [];
    $scope.selectedInterface = "";
    $scope.accessPoints = [];
    $scope.selectedAP = {};
    $scope.scanning = false;
    $scope.key = "";
    $scope.connected = true;
    $scope.connecting = false;
    $scope.noNetworkFound = false;
    $scope.loading = false;
    $scope.info = '';
    $scope.actions = '';

    $scope.getInterfaces = (function() {
        $scope.interfaces = [];
        $scope.actions = 'loading';
        $api.request({
            module: 'Networking',
            action: 'getClientInterfaces'
        }, function(response) {
            $scope.actions = '';
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
                $scope.reloadData();
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
                $scope.loading = false;
                if (response.connected) {
                    $scope.connected = true;
                    $scope.connectedInterface = response.interface;
                    $scope.connectedSSID = response.ssid;
                    $scope.connectedIP = response.ip;
                } else {
                    $scope.connected = false;
                    //$scope.getInterfaces();
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

    $scope.interfaceActions = (function(type, wlan) {
        $scope.actions = 'loading';
        $api.request({
            module: 'Networking',
            action: 'interfaceActions',
            type: type,
            interface: wlan
        }, function(response) {
            $scope.actions = '';
            if (response.error === undefined) {
                // reload interfaces in monitor command cases
                if (type === 3 || type === 4) {
                    $scope.getInterfaces();
                }
            }
        });
    });

    $scope.getInfoData = (function(type) {
        $scope.info = 'loading';
        $api.request({
            module: 'Networking',
            action: 'getInfoData',
            type: type
        }, function(response) {
            if (response.error === undefined) {
                $scope.info = response.info;
            }
        });
    });

    $scope.reloadData = (function() {
        $scope.loading = true;
        $scope.checkConnection();
        if ($scope.connected) {
            $scope.getInterfaces();
        }
    });

    $scope.reloadData();
}]);

registerController('NetworkingFirewallController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.firewallUpdated = false;
    $scope.WANSSHAccess = false;
    $scope.WANUIAccess = false;
    $scope.showFirewallConfig = false;

    $scope.reloadData = (function() {
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

    $api.onDeviceIdentified(function(device, scope) {
        scope.showFirewallConfig = $api.deviceConfig.showFirewallConfig;
    }, $scope);

    //$scope.reloadData();
}]);

registerController('NetworkingMACAddressesController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.interfaces = [];
    $scope.selectedInterface = "";
    $scope.newMac = "";
    $scope.modifyingMAC = false;
    $scope.forceReload = false;
    $scope.loading = false;

    $scope.reloadData = (function() {
        $scope.loading = true;
        $api.request({
            module: 'Networking',
            action: 'getMacData'
        }, function(response) {
            if (response.error === undefined) {
                $scope.loading = false;
                $scope.interfaces = response;
                $scope.selectedInterface = Object.keys(response)[0];
            }
        });
    });

    $scope.setMac = (function() {
        $scope.modifyingMAC = true;
        $api.request({
            module: 'Networking',
            action: 'setMac',
            interface: $scope.selectedInterface,
            mac: $scope.newMac,
            forceReload: $scope.forceReload
        }, function(response) {
            if (response.error === undefined) {
                $scope.newMac = "";
                $timeout(function(){
                    $scope.modifyingMAC = false;
                    $scope.reloadData();
                }, 6000);
            }
        });
    });

    $scope.setRandomMac = (function() {
        $scope.modifyingMAC = true;
        $api.request({
            module: 'Networking',
            action: 'setRandomMac',
            interface: $scope.selectedInterface,
            forceReload: $scope.forceReload
        }, function(response) {
            if (response.error === undefined) {
                $scope.newMac = "";
                $timeout(function(){
                    $scope.modifyingMAC = false;
                    $scope.reloadData();
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
                    $scope.reloadData();
                }, 6000);
            }
        });
    });

    $scope.reloadData();
}]);

registerController('NetworkingAdvancedController', ['$api', '$scope', '$timeout', function($api, $scope, $timeout) {
    $scope.loading = false;
    $scope.hostnameUpdated = false;
    $scope.wirelessReset = false;
    $scope.wirelessUpdated = false;
    $scope.data = {
        hostname: "Pineapple",
        wireless: ""
    };

    $scope.reloadData = (function() {
        $scope.loading = true;
        $scope.data['wireless'] = 'Loading...';
        $api.request({
            module: 'Networking',
            action: 'getAdvancedData'
        }, function(response) {
            if (response.error === undefined) {
                $scope.loading = false;
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
                }, 3000);
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
                    $scope.reloadData();
                    $scope.wirelessReset = false;
                }, 3000);
            }
        });
    });

    $scope.saveWirelessConfig = (function() {
        $api.request({
            module: 'Networking',
            action: 'saveWirelessConfig',
            wireless: $scope.data['wireless']
        }, function(response) {
            if (response.success === true) {
                $scope.wirelessUpdated = true;
                $timeout(function(){
                    $scope.reloadData();
                    $scope.wirelessUpdated = false;
                }, 3000);
            }
        });
    });

    //$scope.reloadData();
}]);

registerController("OUILookupController", ['$api', '$scope', '$timeout', '$http', function($api, $scope, $timeout, $http) {
    $scope.macAddress = "";
    $scope.vendor = "";

    $scope.isOUIPresent = $api.ouiPresent;

    $scope.downloadOUIDatabase = function () {
        $scope.gettingOUI = true;
        $scope.ouiLoading = true;
        $api.loadOUIFile((function() {
            $scope.ouiLoading = false;
        }));
    };

    $scope.lookupMACAddress = function() {
        if (!$api.ouiPresent()) {
            return;
        }

        $scope.ouiLoading = true;
        var mac = convertMACAddress($scope.macAddress.trim());
        $api.lookupOUI(mac, (function(text) {
            $scope.vendor = text;
            $scope.ouiLoading = false;
        }));
    };

    $scope.removeOUIDatabase = function() {
        $api.deleteOUI((function() {
            $scope.ouiLoading = false;
            $scope.gettingOUI = false;
        }));
    };
}]);
