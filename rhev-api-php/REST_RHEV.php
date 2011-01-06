<?php
/*
 * Author: Tomas Von Veschler <tvvcox gmail>
 * Url: https://code.google.com/p/rhev-api-labs/
 * License: MIT http://www.opensource.org/licenses/mit-license.php
 */

class REST_RHEV {

    var $url;
    var $response=null;
    var $conn; // a REST object

    function __construct($url) {
        $this->url = $url;
        $this->conn = REST::connection();
    }

    static function api($entry) {
        return new REST_RHEV_Resource($entry);
    }
}

class REST_RHEV_Resource extends REST_RHEV {

    function __construct($url, $response=null) {
        $this->response = $response;
        parent::__construct($url);
    }

    // Overloaded properties
    function __get($name) {
        // retrieve response on demand
        if (!$this->response) {
            $res = $this->conn->call('GET', $this->url);
            if (!$res) {
                return false;
            }
            $this->response = $res;
        }
        // throw error if user tries to access a property that doesnt exist?
        return isset($this->response->$name) ? $this->response->$name : null;
    }

    // Overloaded methods
    function __call($method, $args) {
        switch (count($args)) {
            // get collection: $api->hosts()
            case 0:
                $new_url = $this->url . '/' . $method;
                return new REST_RHEV_Collection($new_url);
            // get resource by ID: $api->hosts(6)
            case 1:
                $new_url = $this->url . '/' . $method . '/' . $args[0];
                return new REST_RHEV_Resource($new_url);
            // get list from search: $api->hosts('name', 'foo')
            case 2:
                $new_url = $this->url . '/' . $method . '?search='.urlencode($args[0].'='.$args[1]);
                return new REST_RHEV_Collection($new_url);
        }
    }

    // &$action_response is useful when HTTP transaction was sucessful
    // but the actual action failed. If the action failed, we return false
    // with the failed result filled into $action_response
    function action($action, $action_obj=null, &$action_response=null) {
        $url = $this->url . '/' . $action;
        if (!is_object($action_obj)) {
            $action_obj = new stdClass(); // mimic an <action/> tag
        }
        $res = $this->conn->call('POST', $url, $action_obj);
        if (!$res) { // code 400/500
            return false;
        }
        // failed actions are:
        // HTTP code 200 but fault response
        $action_response = $res;
        return isset($res->fault) ? false : $res;
    }

    function delete() {
        return $this->conn->call('DELETE', $this->url);
    }

    function update($new_obj) {
        return $this->conn->call('PUT', $this->url, $new_obj);
    }
}

class REST_RHEV_Collection extends REST_RHEV implements IteratorAggregate {

    function getIterator() {
        // retrieve response on demand
        if ($this->response === null) {
            $res = $this->conn->call('GET', $this->url);
            $ret = current($res);
            $this->response = array();
            if ($ret) { // non empty result set
                // convert array of stdClass() objects to array of api objects
                foreach ($ret as $obj) {
                    $this->response[] = new REST_RHEV_Resource($obj->href, $obj);
                }
            }
        }
        return new ArrayIterator($this->response);
    }

    function create($new_obj) {
        return $this->conn->call('POST', $this->url, $new_obj);
    }
}

?>