# Wifi Pineapple Panel

The idea of this project is to have a **better panel** for the pineapple NANO/TETRA  
To install it, you just have to copy the src folder to / of the pineapple.


# Changes

The following functionalities are modified looking for a better user experience

## General:
 - Compress PNG images (size -55K)
 - Compress SVG images (size -7K)
 - Update Bootstrap to 3.4.1 (size +2K)
 - Fix mobile view
 - Add Chevron icon to accordions (size +1K)
 - Change notification time from 6000 to 20000 (decrease RPM from 10 to 3)
 - Project minification (Optional: use packer tools)

## Recon:
 - Code refactor in module.php
 - Add results counter in titles with badges
 - Fix column alignment

## Clients:
 - Add loading indicator
 - Change default text logic

## PineAP:
 - Configure used interface

## Logging:
 - Fire data loading on open accordion
 - Add PineAP Logs loading indicator
 - Save filters in cookies

## Network:
 - Add wireless config editor

## Setup:
 - Fix character bad used


# Notes

 1. For edit notification timer you can use this
 ```bash
 # sed -i 's/OLD-VALUE/NEW-VALUE/' FILE
 sed -i 's/20000/30000/' src/pineapple/js/controllers.js
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
