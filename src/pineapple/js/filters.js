(function () {
    angular.module('pineapple')
        .filter('timesince', function () {
            return function (input) {
                var then = new Date(input * 1000);
                var now = new Date();
                var hoursSince = Math.round(Math.abs(now - then) / 1000 / 60 / 60);
                var minutesSince = Math.round(Math.abs(now - then) / 60 / 1000);
                var secondsSince = Math.round(Math.abs(now - then) / 1000);
                if (secondsSince >= 1 && secondsSince < 60) {
                    return secondsSince + ' ' + (secondsSince === 1 ? 'second' : 'seconds') + ' ago';
                } else if (minutesSince >= 1 && minutesSince < 60) {
                    return minutesSince + ' ' + (minutesSince === 1 ? 'minute' : 'minutes') + ' ago';
                } else if (hoursSince >= 1 && hoursSince < 24) {
                    return hoursSince + ' ' + (hoursSince === 1 ? 'hour' : 'hours') + ' ago';
                } else {
                    var hours = then.getHours();
                    var minutes = then.getMinutes();
                    var month = then.getMonth();
                    var day = then.getDay();
                    var year = then.getYear();
                    return 'at ' + (year + 1990) + '-' + month + '-' + day + ' ' + (hours % 12 === 0 ? '12' : (hours % 12).toString()) + ':' + minutes + (hours > 12 ? ' PM' : ' AM');
                }
            }
        })

        .filter('timesincedate', function () {
            return function (input) {
                if (input === undefined) {
                    return "";
                }
                var then = utcDate(input);
                var now = new Date();
                var hoursSince = Math.round(Math.abs(now - then) / 1000 / 60 / 60);
                var minutesSince = Math.round(Math.abs(now - then) / 60 / 1000);
                var secondsSince = Math.round(Math.abs(now - then) / 1000);
                if (secondsSince >= 1 && secondsSince < 60) {
                    return secondsSince + ' ' + (secondsSince === 1 ? 'second' : 'seconds') + ' ago';
                } else if (minutesSince >= 1 && minutesSince < 60) {
                    return minutesSince + ' ' + (minutesSince === 1 ? 'minute' : 'minutes') + ' ago';
                } else {
                    var hours = ("0" + then.getHours()).slice(-2);
                    var minutes = ("0" + then.getMinutes()).slice(-2);
                    var month = ("0" + (then.getMonth() + 1)).slice(-2);
                    var day = ("0" + then.getDate()).slice(-2);
                    var year = then.getYear();
                    return 'at ' + (year + 1900) + '-' + month + '-' + day + ' ' + hours + ':' + minutes;
                }
            }
        })

        .filter('timesinceepoch', function () {
            return function (input) {
                if (input === undefined) {
                    return "";
                }
                var then = new Date(input * 1000);
                var now = new Date();
                var hoursSince = Math.round(Math.abs(now - then) / 1000 / 60 / 60);
                var minutesSince = Math.round(Math.abs(now - then) / 60 / 1000);
                var secondsSince = Math.round(Math.abs(now - then) / 1000);
                if (secondsSince >= 1 && secondsSince < 60) {
                    return secondsSince + ' ' + (secondsSince === 1 ? 'second' : 'seconds') + ' ago';
                } else if (minutesSince >= 1 && minutesSince < 60) {
                    return minutesSince + ' ' + (minutesSince === 1 ? 'minute' : 'minutes') + ' ago';
                } else {
                    var hours = ("0" + then.getHours()).slice(-2);
                    var minutes = ("0" + then.getMinutes()).slice(-2);
                    var month = ("0" + then.getMonth()).slice(-2);
                    var day = ("0" + then.getDay()).slice(-2);
                    var year = then.getYear();
                    return 'at ' + (year + 1900) + '-' + month + '-' + day + ' ' + hours + ':' + minutes;
                }
            }
        })

        .filter('utcToBrowser', function () {
            return function (input) {
                if (input === undefined) {
                    return "";
                }
                var d = new Date(input + " UTC");

                var day = d.getDate();
                var month = d.getMonth();
                var year = d.getFullYear();

                return day + ' ' + month+1 + ' ' + year;
            }
        })

        .filter('rawHTML', ['$sce', function ($sce) {
            return function (input) {
                return $sce.trustAsHtml(input);
            }
        }])

        .filter('roundCeil', function () {
            return function (input) {
                return Math.ceil(input);
            }
        });
})();