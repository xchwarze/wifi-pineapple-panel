registerController("NotesController", ['$api', '$scope', function($api, $scope){
    $scope.macs = [];
    $scope.ssids = [];
    $scope.error = null;

    $scope.getNotes = function() {
    	$api.request({
    		module: "Notes",
    		action: "getNotes"
    	}, function(response) {
    		if (response.error !== undefined) {
    			$scope.error = response.error;
    		} else {
    			$scope.macs = response.macs;
                $scope.ssids = response.ssids;
    		}
    	});
    };

    $scope.deleteNote = function($event) {
        var key = $event.target.getAttribute('key');
        $api.request({
            module: "Notes",
            action: "deleteNote",
            key: key
        }, function() {
            $scope.getNotes();
        });
    };

    $scope.downloadNotes = function() {
        $scope.getNotes();
        $api.request({
            module: "Notes",
            action: "downloadNotes"
        }, function(response) {
            if (response.error === undefined) {
                window.location = '/api?download=' + response.download;
            }
        });
    };

    $scope.getNotes();
}]);