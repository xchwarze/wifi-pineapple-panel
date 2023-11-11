# Wifi Pineapple Panel

The idea of this project is to have a better panel for the pineapple NANO/TETRA  
To install it, you just have to copy the contents of the src folder to / of the pineapple and reboot.


# Changes

The following functionalities are modified looking for a better user experience

## General:
 - Compress PNG images (size -55K)
 - Compress SVG images (size -7K)
 - Update Bootstrap to 3.4.1 (size +2K)
 - Rebuild mobile view
 - Add Chevron icon to accordions (size +1K)
 - Change notification time from 6000 to 30000 (decrease RPM from 10 to 2)
 - Added more refresh buttons
 - Fixed several bugs found in the panel
 - Expose AngularJS Pineapple API in JS window
 - Add timeout and prevent duplicated request in API service
 - Refactor all indexedDB code
 - Removed use of php7-mod-sockets and php7-mod-openssl (size -200K)
 - Implemented use of uclient-fetch as a replacement for wget and file_get_contents(https)

## Dashboard
 - Change update time from 5000 to 10000 (decrease RPM from 12 to 6)

## Recon:
 - Code refactor in module.php
 - Add results counter in titles with badges
 - Fix column alignment

## Clients:
 - Add loading indicator
 - Change default text logic

## PineAP:
 - Configure used monitor interface (pineapd pineap_interface)
 - Configure used source interface (pineapd source_mac grabber)
 - Show pineapd service errors

## Logging:
 - Fire data loading on open accordion
 - Add PineAP Logs loading indicator
 - Save filters in cookies

## Network:
 - Add tabs
 - Add "Wireless raw config editor" section
 - Add "Info" section
 - Add "Interface actions" section
 - Decrease initial requests (from 8 to 3)
 - Use new feed for out.txt updates

## Advanced:
 - Add tabs
 - Add "Manual upgrade" section
 - Decrease initial requests (from 8 to 3)
 - Use Universal Wifi pineapple hardware cloner downloads as update feed
 - Add "Keep settings and retain the current configuration" checkbox

## Modules:
 - Refactor in Modules.php
 - Add support for injectJS in modules manifest
 - Use new modules feed: https://github.com/xchwarze/wifi-pineapple-community/tree/main/modules


# Notes

1. For edit notification timer you can use this
 ```bash
 # sed -i 's/OLD-VALUE/NEW-VALUE/' FILE
 sed -i 's/30000/60000/' src/pineapple/js/controllers.js
```

2. To open the menu on hover uncomment this in src/pineapple/main.css
 ```css
.sidebar:hover {
	margin-left: 0;
}
.menu-toggle {
	display: none !important;
}
```

3. To develop locally you can point your panel to the pinapple replacing the src/pineapple/api/index.php with this
 ```php
<?php
$endpoint = 'http://172.16.42.1:1471/api/index.php';
$sessid = 'xxxxxxxxxxxxxxxxxxxxxxxxx';
$xsrftoken = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID={$sessid}");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-XSRF-TOKEN: {$xsrftoken}"]);

$response = curl_exec($ch);
curl_close($ch);
header('Content-Type: application/json');
echo $response;
```
 