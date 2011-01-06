<?php
$url = 'https://example.com:8443';
$entry = '/rhevm-api-powershell'; // NOTICE: no trailing slash
$user = 'rhevadmin@example.com'; // Format: user@dmain.com
$pass = 'XXX';

// function to help debug for HTML apps
function printr($var) {
    if ($var === null) {
        $ret = '(bool) null';
    } elseif ($var === false) {
        $ret = '(bool) false';
    } elseif ($var === true) {
        $ret = '(bool) true';
    } else {
        $ret = print_r($var, 1);
    }
    echo "<pre>$ret</pre>";
}

?>