registerController('SetupController', ['$api', '$scope', '$interval', '$timeout',  function($api, $scope, $interval, $timeout) {
    $scope.ssid = '';
    $scope.wpaPassword = '';
    $scope.confirmWpaPassword = '';
    $scope.rootPassword = '';
    $scope.confirmRootPassword = '';
    $scope.selectedTimeZone = '';
    $scope.selectedCountryCode = '';
    $scope.macFilterMode = '';
    $scope.ssidFilterMode = '';
    $scope.device = '';
    $scope.error = '';
    $scope.changes = "";
    $scope.hideOpenAP = false;
    $scope.verified = false;
    $scope.eula = false;
    $scope.license = false;
    $scope.complete = false;
    $scope.booted = false;
    $scope.showChanges = false;

    $scope.timeZones = [
        { value: 'GMT+12', description: "(GMT-12:00) Eniwetok, Kwajalein" },
        { value: 'GMT+11', description: "(GMT-11:00) Midway Island, Samoa" },
        { value: 'GMT+10', description: "(GMT-10) Hawaii" },
        { value: 'GMT+9',  description: "(GMT-9) Alaska" },
        { value: 'GMT+8',  description: "(GMT-8) Pacific Time (US & Canada)" },
        { value: 'GMT+7',  description: "(GMT-7) Mountain Time (US & Canada)" },
        { value: 'GMT+6',  description: "(GMT-6) Central Time (US & Canada), Mexico City" },
        { value: 'GMT+5',  description: "(GMT-5) Eastern Time (US & Canada), Bogota, Lima" },
        { value: 'GMT+4',  description: "(GMT-4) Atlantic Time (Canada), Caracas, La Paz" },
        { value: 'GMT+3',  description: "(GMT-3) Brazil, Buenos Aires, Georgetown" },
        { value: 'GMT+2',  description: "(GMT-2) MidAtlantic" },
        { value: 'GMT+1',  description: "(GMT-1) Azores, Cape Verde Islands" },
        { value: 'UTC',    description: "(UTC) Western Europe Time, London, Lisbon, Casablanca"},
        { value: 'GMT-1',  description: "(GMT+1) Brussels, Copenhagen, Madrid, Paris" },
        { value: 'GMT-2',  description: "(GMT+2) Kaliningrad, South Africa" },
        { value: 'GMT-3',  description: "(GMT+3) Baghdad, Riyadh, Moscow, St. Petersburg" },
        { value: 'GMT-4',  description: "(GMT+4) Abu Dhabi, Muscat, Baku, Tbilisi" },
        { value: 'GMT-5',  description: "(GMT+5) Ekaterinburg, Islamabad, Karachi, Tashkent" },
        { value: 'GMT-6',  description: "(GMT+6) -Almaty, Dhaka, Colombo" },
        { value: 'GMT-7',  description: "(GMT+7) Bangkok, Hanoi, Jakarta" },
        { value: 'GMT-8',  description: "(GMT+8) Beijing, Perth, Singapore, Hong Kong" },
        { value: 'GMT-9',  description: "(GMT+9) Tokyo, Seoul, Osaka, Sapporo, Yakutsk" },
        { value: 'GMT-10', description: "(GMT+10) Eastern Australia, Guam, Vladivostok" },
        { value: 'GMT-11', description: "(GMT+11) Magadan, Solomon Islands, New Caledonia" },
        { value: 'GMT-12', description: "(GMT+12) Auckland, Wellington, Fiji, Kamchatka" }
    ];

    $scope.countryCodes = [
        { value: 'US', country: "United States" },
        { value: 'DZ', country: "Algeria" },
        { value: 'AR', country: "Argentina" },
        { value: 'AU', country: "Australia" },
        { value: 'AT', country: "Austria" },
        { value: 'BH', country: "Bahrain" },
        { value: 'BM', country: "Bermuda" },
        { value: 'BO', country: "Bolivia" },
        { value: 'BR', country: "Brazil" },
        { value: 'BG', country: "Bulgaria" },
        { value: 'CA', country: "Canada" },
        { value: 'CL', country: "Chile" },
        { value: 'CN', country: "China" },
        { value: 'CO', country: "Colombia" },
        { value: 'CR', country: "Costa Rica" },
        { value: 'CS', country: "Cyprus" },
        { value: 'CZ', country: "Czech Republic" },
        { value: 'DK', country: "Denmark" },
        { value: 'DO', country: "Dominican Republic" },
        { value: 'EC', country: "Ecuador" },
        { value: 'EG', country: "Egypt" },
        { value: 'SV', country: "El Salvador" },
        { value: 'EE', country: "Estonia" },
        { value: 'FI', country: "Finland" },
        { value: 'FR', country: "France" },
        { value: 'DE', country: "Germany" },
        { value: 'GR', country: "Greece" },
        { value: 'GT', country: "Guatemala" },
        { value: 'HN', country: "Honduras" },
        { value: 'HK', country: "Hong Kong" },
        { value: 'IS', country: "Iceland" },
        { value: 'IN', country: "India" },
        { value: 'ID', country: "Indonesia" },
        { value: 'IE', country: "Ireland" },
        { value: 'PK', country: "Islamic Republic of Pakistan" },
        { value: 'IL', country: "Israel" },
        { value: 'IT', country: "Italy" },
        { value: 'JM', country: "Jamaica" },
        { value: 'JP3', country: "Japan" },
        { value: 'JO', country: "Jordan" },
        { value: 'KE', country: "Kenya" },
        { value: 'KW', country: "Kuwait" },
        { value: 'LB', country: "Lebanon" },
        { value: 'LI', country: "Liechtenstein" },
        { value: 'LT', country: "Lithuania" },
        { value: 'LU', country: "Luxembourg" },
        { value: 'MU', country: "Mauritius" },
        { value: 'MX', country: "Mexico" },
        { value: 'MA', country: "Morocco" },
        { value: 'NL', country: "Netherlands" },
        { value: 'NZ', country: "New Zealand" },
        { value: 'NO', country: "Norway" },
        { value: 'OM', country: "Oman" },
        { value: 'PA', country: "Panama" },
        { value: 'PE', country: "Peru" },
        { value: 'PH', country: "Philippines" },
        { value: 'PL', country: "Poland" },
        { value: 'PT', country: "Portuagal" },
        { value: 'PR', country: "Puerto Rico" },
        { value: 'QA', country: "Qatar" },
        { value: 'KR', country: "Republic of Korea (South Korea)" },
        { value: 'RO', country: "Romania" },
        { value: 'RU', country: "Russia" },
        { value: 'SA', country: "Saudi Arabia" },
        { value: 'SG', country: "Singapore" },
        { value: 'SI', country: "Slovenia" },
        { value: 'SK', country: "Slovak Republic" },
        { value: 'ZA', country: "South Africa" },
        { value: 'ES', country: "Spain" },
        { value: 'LK', country: "Sri Lanka" },
        { value: 'CH', country: "Switzerland" },
        { value: 'TW', country: "Taiwan" },
        { value: 'TH', country: "Thailand" },
        { value: 'TT', country: "Trinidad and Tobago" },
        { value: 'TN', country: "Tunisia" },
        { value: 'TR', country: "Turkey" },
        { value: 'UA', country: "Ukraine" },
        { value: 'AE', country: "United Arab Emirates" },
        { value: 'GB', country: "United Kingdom" },
        { value: 'UY', country: "Uraguay" },
        { value: 'VE', country: "Venezuela" },
        { value: 'VN', country: "Vietnam" }
    ];

    $scope.getDeviceName = function(){
        $api.request({
            system: 'setup',
            action: 'getDeviceName'
        }, function(response) {
            $scope.device = response.device;
        });
    };
    $scope.getDeviceName();

    $scope.doSetup = function(){
        $scope.error = '';
        $api.request({
            system: 'setup',
            action: 'performSetup',
            rootPassword: $scope.rootPassword,
            confirmRootPassword: $scope.confirmRootPassword,
            timeZone: $scope.selectedTimeZone.value,
            managementSSID: $scope.managementSSID,
            managementPass: $scope.managementPass,
            confirmManagementPass: $scope.confirmManagementPass,
            hideManagementAP: $scope.hideManagementAP,
            disableManagementAP: $scope.disableManagementAP,
            openSSID: $scope.openSSID,
            hideOpenAP: $scope.hideOpenAP,
            countryCode: $scope.selectedCountryCode.value,
            macFilterMode: $scope.macFilterMode,
            ssidFilterMode: $scope.ssidFilterMode,
            WANSSHAccess: $scope.WANSSHAccess,
            WANUIAccess: $scope.WANUIAccess,
            eula: $scope.eula,
            license: $scope.license
        }, function(response){
            if (response.error === undefined) {
                $scope.verified = false;
                $scope.complete = true;
                $("#loginModal").remove();
                $timeout(function() {
                    window.location = '/';
                }, 5000);
            } else {
                $scope.error = response.error;
            }
        });
    };

    $scope.checkButton = function(){
        $api.request({
            system: 'setup',
            action: 'checkButtonStatus'
        }, function(response){
            $scope.booted = response.booted;
            if (response.buttonPressed) {
                $('#verificationModal').modal('hide');
                $interval.cancel($scope.buttonCheckInterval);
                $scope.verified = true;
            }
        });
    };

    $scope.getChanges = function() {
        $api.request({
            system: 'setup',
            action: 'getChanges'
        }, function(response) {
            if (response.changes !== null) {
                $scope.showChanges = true;
                $scope.changes = response.changes;
                $scope.fwversion = response.fwversion
            } else {
                $scope.showChanges = false;
            }
        });
    };
    $scope.getChanges();

    $scope.populateFields = function() {
        $api.request({
            system: 'setup',
            action: 'populateFields'
        }, function(response) {
            if (response.error === undefined) {
                $scope.openSSID = response.openSSID;
                $scope.hideOpenAP = response.hideOpenAP;
            }
        });
    };

    $scope.getStarted = function() {
        if($scope.showChanges) {
            $scope.showChangesModal();
        } else {
            $scope.showVerificationModal();
        }
    };

    $scope.toggleMACMode = function() {
        if ($scope.macFilterMode === 'Allow') {
            $scope.macFilterMode = 'Deny';
        } else {
            $scope.macFilterMode = 'Allow';
        }
    };

    $scope.toggleSSIDMode = function() {
        if ($scope.ssidFilterMode === 'Allow') {
            $scope.ssidFilterMode = 'Deny';
        } else {
            $scope.ssidFilterMode = 'Allow';
        }
    };

    $scope.showChangesModal = function(){
        $('#changesModal').modal(true);
    };

    $scope.showVerificationModal = function(){
        $('#verificationModal').modal(true);
        $scope.buttonCheckInterval = $interval($scope.checkButton, 1000);
    };

    $scope.$on('$destroy', function() {
        $interval.cancel($scope.buttonCheckInterval);
    });

    $('#welcomeModal').modal(true);
    $scope.populateFields();
}]);