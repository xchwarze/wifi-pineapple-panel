(function(){
    var pineapple = angular.module('pineapple', ['ngRoute', 'ngCookies'])

    .config(['$routeProvider', '$controllerProvider', '$compileProvider', '$filterProvider', '$provide', function($routeProvider, $controllerProvider, $compileProvider, $filterProvider, $provide) {
        
        pineapple.controllerProvider = $controllerProvider;
        pineapple.compileProvider    = $compileProvider;
        pineapple.routeProvider      = $routeProvider;
        pineapple.filterProvider     = $filterProvider;
        pineapple.provide            = $provide;
    }])
    .run(['$api', function($api){
        pineapple.routeProvider
        .when('/modules/:moduleName', {
            templateUrl: function(params) {
                return 'modules/'+ params.moduleName +'/module.html';
            },
            controller: function() {
                resizeModuleContent();
                collapseNavBar();
            },
            resolve: {
                jsLoader: ['$route', function($route) {
                    return $.getScript('modules/'+ $route.current.params.moduleName +'/js/module.js');
                }]
            }
        })
        .otherwise({
            redirectTo: '/modules/Dashboard'
        });
    }])
})();
