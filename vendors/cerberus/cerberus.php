<?php

require_once dirname(__FILE__) . DS . "cerb_api.php";
class Cerberus {

    private $_cerberus;
    private $_url; // https://example.com/rest/

    public function __construct($url, $access_key, $secret_key) {
        $this->_url = $url;
        $this->_cerberus = new Cerb_WebAPI($access_key, $secret_key);
    }

    public function getResponseHeaders() {
        return $this->_cerberus->getResponseHeaders();
    }

    public function get($endpoint) {
        return $this->_cerberus->get($this->_url . $endpoint);
    }

    // Used for the new Cerb Records API
    public function search($endpoint, $query) {
        return $this->_cerberus->get($this->_url . $endpoint . '?' . $query);
    }

    public function post($endpoint, array $fields) {
        return $this->_cerberus->post($this->_url . $endpoint, $fields);
    }

    public function put($endpoint, array $fields) {
        return $this->_cerberus->put($this->_url . $endpoint, $fields);
    }

    public function patch($endpoint, array $fields) {
        return $this->_cerberus->patch($this->_url . $endpoint, $fields);
    }
}
