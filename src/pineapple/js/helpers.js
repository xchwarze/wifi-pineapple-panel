function registerController(name, controller) {
    angular.module('pineapple').controllerProvider.register(name, controller);
}

function resizeModuleContent() {
    var offset = 50;
    var height = ((window.innerHeight > 0) ? window.innerHeight : screen.height) - 1;
    height = height - offset;
    if (height < 1) height = 1;
    if (height > offset) {
        $(".module-content").css("min-height", (height) + "px");
    }
}

function collapseNavBar() {
    var width = (window.innerWidth > 0) ? window.innerWidth : screen.width;
    if (width < 768) {
        $('div.navbar-collapse').removeClass('in');
    } else {
        $('div.navbar-collapse').addClass('in');
    }
}

function convertMACAddress(mac) {
    var pattern = /([-: ])/igm;
    return mac.replace(pattern, ":");
}

function locallyAssigned(mac) {
    return (parseInt('0x' + mac.split(':')[0]) & 0x02) !== 0;
}

function annotateMacs() {
    var mac_rows = $('td, .autoselect').filter(
        function() {
            return /^[0-9a-f]{1,2}([.:-])[0-9a-f]{1,2}(?:\1[0-9a-f]{1,2}){4}$/i.test(this.textContent.trim());
        });
    mac_rows.filter(function() {
            return locallyAssigned(this.textContent.trim());
        }).prop('title', 'This MAC was likely locally assigned and was not assigned by the hardware vendor. This could be the result of MAC randomization, Spoofing, or a vendor that has not registered with the IEEE Registration Authority.').css('color', '#31708f');
    mac_rows.filter(function() {
            return !locallyAssigned(this.textContent.trim());
        }).prop('title', 'This MAC was likely globally assigned by the hardware vendor. It has probably not been randomized for privacy.');
}

function utcDate(timestampStr) {
    var a = timestampStr.split(' ');
    var dmy = a[0].split('-');
    var hms = a[1].split(':');

    return new Date(Date.UTC(dmy[0], dmy[1] - 1, dmy[2], hms[0], hms[1], hms[2]));
}

function selectElement(elem) {
    var selectRange = document.createRange();
    selectRange.selectNodeContents(elem);
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(selectRange);
}

$('html').click(function(e){
    var elem = e.toElement;
    if (elem !== undefined && elem.classList.contains('autoselect')) {
        selectElement(elem);
    }
});

$(window).resize(function() {
    resizeModuleContent();
});

setInterval(annotateMacs, 1500);
