<?php namespace pineapple;

header('Content-Type: application/json');

require_once('pineapple.php');
require_once('API.php');
$api = new API();
echo $api->magic();
