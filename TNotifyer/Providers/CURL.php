<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

use TNotifyer\Providers\Log;
use TNotifyer\Exceptions\InternalException;

/**
 * 
 * Object to provide a CURL functional.
 * 
 */
class CURL {
	/**
	 * @var bool make json decode after request
	 */
	public $make_json_decode = true;

	/**
	 * @var bool silent on error
	 */
	public $silent_on_error = false;

	/**
	 * @var string last error message
	 */
	public $last_error_message;

	/**
	 * @var mixed last request {'url','method','postfields'}
	 */
	public $last_request;


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

		$ch = curl_init();
		if ($ch === false) {
			$msg = 'Can not init curl';
			Log::put('error', $msg); // log error
			if (!$this->silent_on_error) echo $msg;
			$this->last_error_message = $msg;
			throw new InternalException($msg);
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		if (!empty($headers))
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		// option to make more stable connection
		// curl_setopt($ch, CURLOPT_SSLVERSION, 3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

		// request method
		if ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
		} elseif ($method != 'GET') {
			// Specify the HTTP method
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		}

		// post data for request
		if (!empty($postfields)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
		}

		// make request
		$result = @curl_exec($ch);
		$error = @curl_error($ch);

		// one more try to make request if empty response
		if (empty($result)) {
			$result = @curl_exec($ch);
			$error = @curl_error($ch);
		}

		@curl_close($ch);

		if (empty($result)) {
			// no data
			$msg = "No data from $url";
			if (!empty($error)) $msg .= ' | Error: ' . $error;
			Log::put('error', $msg); // log error
			if (!$this->silent_on_error) echo $msg;
			$this->last_error_message = $msg;
			return false;
		}

		if ($this->make_json_decode) {
			$response = @json_decode($result, true);
			if (empty($response)) {
				// wrong data
				$msg = "Can't decode JSON: " . (json_last_error() != JSON_ERROR_NONE)? json_last_error_msg() : '';
				Log::put('error', $msg, ['request' => $this->last_request, 'response' => $result]); // log error
				if (!$this->silent_on_error) {
					echo $msg;
					print_r($result);
				}
				$this->last_error_message = $msg;
				return false;
			}
		} else {
			$response = $result;
		}
		
		return $response;
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

		$ch = curl_init();
		if ($ch === false) {
			$msg = 'Can not init curl';
			Log::put('error', $msg); // log error
			if (!$this->silent_on_error) echo $msg;
			$this->last_error_message = $msg;
			throw new InternalException($msg);
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, true);
		if (!empty($headers))
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// request method
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);

		// options to make more stable connection
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

		$result = curl_exec($ch);
		$error = curl_error($ch);
		curl_close($ch);

		if (!empty($error)) {
			// has error
			$msg = 'HEAD request error: ' . $error;
			Log::put('warning', $msg); // log warning
			if (!$this->silent_on_error) echo $msg;
			$this->last_error_message = $msg;
			return false;
		}
		
		return $result;
	}

	/**
	 * Check a status of website
	 * 
	 * @param string site url
	 * @param int time to sleep before repeat a check (optional)
	 */
	public function pingUrl($url, $time = 2) {
		// $active = !empty(CURL::head($url)); // error on php 5.4
		$active = self::head($url);
		$active = !empty($active);

		// repeat check if down and $time > 0
		if (!$active && $time > 0) {
			set_time_limit($time * 60 + 30);
			sleep($time * 60);
			$active = self::head($url);
			$active = !empty($active);
		}
		
		return $active;
	}

	/**
	 * Get last error message
	 */
	public function last_error_message() {
		return $this->last_error_message;
	}
}
