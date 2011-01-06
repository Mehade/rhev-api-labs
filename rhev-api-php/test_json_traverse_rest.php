<?php
// run it from command line: php test_json_traverse_rest.php

require_once './REST.php';
require_once './REST_RHEV.php';
require_once './config.php'; // defines $url, $user, $pass, $entry

function format_error() {
    $conn = REST::connection();
    $res = $conn->last()->get_response();
    $headers = explode("\n", $conn->last()->headers_sent);
    if (preg_match('#<h1>(.*)</h1>#s', $res, $m)) {
        $res = $m[1];
    } else {
        $res = strip_tags($res);
    }
    print $headers[0]."\n".html_entity_decode($res);
    print "\n--------------------------------------\n";
    //exit;
}

function rest_recursive($resource) {
    // check access to resource
    try {
        $links = $resource->links; sleep(1);
    } catch (Exception $e) {
        format_error(); return;
    }
    echo "Resource $resource->url OK\n--------------------------------------\n";
    if (!$links) {
        return;
    }
    foreach ($links as $link) {
        if (preg_match('/search/', $link->rel)) {
            continue;
        }
        // check access to collections
        try {
            $method = $link->rel;
            $objs = $resource->$method();
            foreach ($objs as $obj) {
            }
            echo "Collection $objs->url OK\n--------------------------------------\n";
            // Check collection resources
            foreach ($objs as $obj) {
                // Test only the first resource in a collection
                rest_recursive($obj);
                break;
            }
        } catch (Exception $e) {
            format_error();
        }
    }
}

$rest = REST::connection($url, $user, $pass);
$api = REST_RHEV::api($entry);

rest_recursive($api);


?>