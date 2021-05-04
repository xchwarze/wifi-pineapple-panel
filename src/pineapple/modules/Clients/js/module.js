registerController("ClientsController", ['$api', '$scope', '$timeout', function($api, $scope, $timeout){
    $scope.clients  = [];
    $scope.loading = false;

    $scope.getClientData = function(){
        $scope.loading = true;
        $api.request({
            module: "Clients",
            action: "getClientData"
        }, function(response) {
            $scope.loading = false;
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