registerController("ClientsController", ['$api', '$scope', '$timeout', function($api, $scope, $timeout){
    $scope.clients  = [];

    $scope.getClientData = function(){
        $api.request({
            module: "Clients",
            action: "getClientData"
        }, function(response) {
            $scope.parseClients(response.clients);
        });
    };

    $scope.parseClients = function($clients) {
        $scope.clients = $clients;
    };

    $scope.kickClient = function(client){
        $api.request({
            module: "Clients",
            action: "kickClient",
            mac: client.mac
        }, function(){
            client['kicking'] = true;
            $timeout(function() {
                client['kicking'] = false;
                $scope.getClientData();
            }, 3000);
        });
    };

    $scope.getClientData();
}]);