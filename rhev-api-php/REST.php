<?php
/*
 * Author: Tomas Von Veschler <tvvcox gmail>
 * Url: https://code.google.com/p/rhev-api-labs/
 * License: MIT http://www.opensource.org/licenses/mit-license.php
 *
 * Required PHP extensions: curl, json, SimpleXML
 */

class REST {

    var $rhev_api_url;
    var $rhev_api_user; // in the form user@domain
    var $rhev_api_pass;
    var $rhev_api_response_format = 'json'; // valid are 'x-yaml', 'xml', 'json'

    // stores last call result (a REST_Result object)
    var $_last;

    /*
     * Don't use this, use REST::connection($url, $user, $pass) instead
     */
    protected function __construct($url, $user, $pass) {
        $this->rhev_api_url = $url;
        $this->rhev_api_user = $user;
        $this->rhev_api_pass = $pass;
    }

    static function &connection($url=null, $user=null, $pass=null) {
        static $conn;
        if (!$conn) {
            if (!extension_loaded('curl')) {
                throw new Exception("php-curl extension not found");
            }
            $conn = new REST($url, $user, $pass);
        }
        return $conn;
    }

    function curl_call($method, $url, $body = false, $user = false, $pass = false) {
        $ret = new REST_Result();
        $this->_last = $ret;
        $ch = curl_init($url);
        if (!$ch) {
            $ret->set_error('Error curl_init: '.$url.' ['.curl_errno($ch).'] '.curl_error($ch));
            return $ret;
        }
        $headers = array(
            'Accept: application/'.$this->rhev_api_response_format,
            'Content-type: application/'.$this->rhev_api_response_format
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        if ($user && $pass) {
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== false) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body); // this works for PUT as well
        }
        $output = curl_exec($ch);
        if ($output === false) {
            $ret->set_error('Error curl_exec: '.$url.' ['.curl_errno($ch).'] '.curl_error($ch));
            return $ret;
        }
        $info = curl_getinfo($ch);
        curl_close($ch);

        list($headers_received, $request_output) = preg_split('|\r\n\r\n|s', $output);
        //list($headers_sent, $request_input) = preg_split('|\r\n\r\n|s', $info['request_header']);

        $ret->headers_sent     = $info['request_header'];
        $ret->headers_received = $headers_received;
        $ret->request_input    = $body;
        $ret->request_output   = $request_output;
        $ret->http_info        = $info;
        $ret->error_msg        = false;

        return $ret;
    }

     /*
     * Prepares the HTTP request, execute it via curl_call() and
     * returns the response in the format specified by $this->rhev_api_response_format
     * Note: Get complete transaccion info with print $rhev->last();
     *
     * @param (string) $method HTTP method
     * @param (string) $path URL path to query
     * @param (string) $input The input request (xml string, json obj, ..)
     * @returns (object) depending on $this->response_format param
     *       or (bool) false on error
     *       or (bool) true if success but empty body (ie. DELETE case)
     */
    function call($method, $path, $input = null) {

        $api_url = $this->rhev_api_url . $path;
        // serialize input
        switch($this->rhev_api_response_format) {
            case 'json':
                $input = $input !== null ? json_encode($input) : null;
                break;
        }
        $res = $this->curl_call($method, $api_url, $input, $this->rhev_api_user, $this->rhev_api_pass);
        if ($res->isError()) {
            throw new Exception($res->__toString());
        }
        $response = $res->get_response();
        // de-serialize response
        switch($this->rhev_api_response_format) {
            case 'json':
                if (!empty($response)) {
                    $obj = json_decode($response);
                    if ($obj == null) {
                        $res->set_error("JSON decode error '$path' ERROR");
                        throw new Exception($res->__toString());
                    }
                // case DELETE returns code 204 but empty body
                } else {
                    return true;
                }
                return $obj;
            case 'xml':
                if (!empty($response)) {
                    return new SimpleXMLElement($response);
                } else {
                    return true;
                }
            default:
                return !empty($response) ? $response : true;
        }
        return false;
    }

    function last() {
        return $this->_last;
    }
}

class REST_Result {
    var $headers_sent;
    var $headers_received;
    var $request_input;
    var $request_output;
    var $http_info;
    var $error_msg = false;

    function isError() {
        if ($this->error_msg) {
            return true;
        }
        $code = $this->http_info['http_code'];
        // Only take a 2xx http code as good
        if (($code < 200) || ($code > 299)) {
            return true;
        }
        return false;
    }

    /*
     * Magic function so its possible to "print $result_object"
     */
    function __toString() {
        if ($this->error_msg) {
            return $this->error_msg;
        }
        $str = "Requested url: " . $this->get_info('url')."\n";
        $str .= "Transaction status: ";
        if ($this->isError()) {
            $str .= "ERROR\n";
        } else {
            $str .= "SUCESS\n";
        }
        $str .= "Debug trace:\n";
        $str .= ">>>>>>>>>>>>>>>>>>>>>>>>>>>>> Sent\n";
        $str .= $this->headers_sent . "\n";
        $str .= $this->request_input . "\n";
        $str .= "<<<<<<<<<<<<<<<<<<<<<<<<<<<<< Received\n";
        $str .= $this->headers_received . "\n\n";
        $str .= $this->request_output . "\n";

        return $str;
    }

    function toHTML() {
        // XXX I suspect this needs an nl2br(), test
        return '<pre>'.htmlentities($this->__toString()).'</pre>';
    }

    /*
     * See http://es2.php.net/manual/en/function.curl-getinfo.php
     */
    function get_info($field) {
        return $this->http_info[$field];
    }

    function get_response() {
        return $this->request_output;
    }

    function set_error($msg) {
        $this->error_msg = $msg;
    }
}

?>