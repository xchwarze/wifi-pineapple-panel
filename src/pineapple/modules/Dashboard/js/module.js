registerController("DashboardOverviewController", ['$api', '$scope', '$interval', function($api, $scope, $interval) {
	$scope.cpu = "";
	$scope.uptime = "";
	$scope.clients = "";
	$scope.ssids = "";
	$scope.newssids = "";

	$scope.populateDashboard = (function() {
		$api.request({
			module: "Dashboard",
			action: "getOverviewData"
		}, function(response) {
			$scope.cpu = response.cpu;
			$scope.uptime = response.uptime;
			$scope.clients = response.clients;
		});
		$api.request({
			module: "PineAP",
			action: "countSSIDs"
		}, function(response) {
			$scope.ssids = response.SSIDs;
			$scope.newssids = response.newSSIDs;
		});
	});

	$scope.populateInterval = $interval(function(){
		$scope.populateDashboard();
	}, 5000);

	$scope.populateDashboard();
	$scope.$on('$destroy', function() {
    	$interval.cancel($scope.populateInterval);
    });
}]);

registerController("DashboardLandingPageController", ['$api', '$scope', function($api, $scope){
	$scope.browsers = [];

	$api.request({
		module: "Dashboard",
		action: "getLandingPageData"
	}, function(response){
		if (response.error === undefined) {
			$scope.browsers = response;
		}
	});
}]);

registerController("DashboardBulletinsController", ['$api', '$scope', function($api, $scope){
	$scope.bulletins = [];

	$scope.getBulletins = function() {
		$scope.loading = true;

		$api.request({
			module: "Dashboard",
			action: "getBulletins"
		}, function(response){
			$scope.loading = false;
			if (response.error !== undefined) {
				$scope.error = response.error;
			} else {
				$scope.bulletins = response;
				$scope.error = false;
			}
		});
	}
}]);