<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

/**
 * 
 * Provides a fake CURL object to make a tests.
 * 
 */
class FakeCURL {

	/**
	 * @var mixed last request {'url','method','postfields'}
	 */
	public $last_request;

	/**
	 * @var mixed value for result of request
	 */
	public $result = '';


	/**
	 * 
	 * Constructor
	 * 
	 * @param mixed response
	 */
	public function __construct($result) {
		$this->result = $result;
	}

	/**
	 * Make http GET or POST request
	 * 
	 * @param string url for request
	 * @param string request method ('GET' by default)
	 * @param array request headers (optional)
	 * @param mixed request body (optional)
	 */
	public function request($url, $method = 'GET', $headers = [], $postfields = '') {
		$this->last_request = [
			'url' => $url,
			'method' => $method,
			'postfields' => $postfields,
		];
		return $this->result;
	}

	/**
	 * Make http GET request
	 * 
	 * @param string url for request
	 * @param array request headers (optional)
	 */
	public function get($url, $headers = []) {
		return self::request($url, 'GET', $headers);
	}

	/**
	 * Make http POST request
	 * 
	 * @param string url for request
	 * @param array request headers (optional)
	 * @param mixed request body (optional)
	 */
	public function post($url, $headers = [], $postfields = '') {
		return self::request($url, 'POST', $headers, $postfields);
	}

	/**
	 * Make http HEAD request
	 * 
	 * @param string url for request
	 * @param array request headers (optional)
	 */
	public function head($url, $headers = []) {
		$this->last_request = [
			'url' => $url,
			'method' => 'HEAD',
		];
		return $this->result;
	}

	/**
	 * Check a status of website
	 * 
	 * @param string site url
	 * @param int time to sleep before repeat a check (optional)
	 */
	public function pingUrl($url, $time = 2) {
		return true;
	}

	/**
	 * Get last error message
	 */
	public function last_error_message() {
		return '';
	}
}
