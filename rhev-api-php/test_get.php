<?php
// run it from command line: php test_get.php

require_once './REST.php';
require_once './REST_RHEV.php';
require_once './config.php'; // defines $url, $user, $pass, $entry

try {

$rest = REST::connection($url, $user, $pass);
$api = REST_RHEV::api($entry);

// API TESTS
// resource tests
$test = $api->hosts(6);
//$test = $api->hosts(9999999999999999);
//$test = $api->hosts(6)->nics('00ea432a-4636-4ab4-9b0c-9d5dfc512ae2');
//$rest->rhev_api_response_format = 'xml';
//$test = $api->vms("ea3e7503-f587-4ffe-871e-854735afe184")->action('stop');
//$test = $api->vms("ea3e7503-f587-4ffe-871e-854735afe184")->action('stop', null, $result);
//$test = $api->vms("ea3e7503-f587-4ffe-871e-854735afe184")->nics("a7e9c230-7d50-412d-94f9-2913db1d2384")->delete();

if ($test === false) {
    print $rest->last(); exit;
}

var_dump($test);
var_dump($test->href); // until here no HTTP request is done
//print_r($rest->last()->get_response());
exit;
//*/
// collection tests
//$test = $api->hosts('name', 'server2.selab.mad.redhat.com');
//$test = $api->hosts('name', '*selab*');
//$test = $api->hosts('status', 'DOWN');
//$test = $api->hosts('status', 'UP');
//$test = $api->hosts();
//$test = $api->hosts(6)->nics();
//$test = $api->nonexistant();

foreach ($test as $resource) {
    echo "\nRESOURCE: ".$resource->name." = ".$resource->id;
}
echo "\n";

} catch (Exception $e) {
    print $e->getMessage(); echo "\n";
    print_r($e->getTrace());
    print $rest->last();
}
?>