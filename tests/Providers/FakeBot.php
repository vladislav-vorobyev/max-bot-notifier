<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

use TNotifyer\Engine\Storage;

/**
 * 
 * Provides the bot behavior for tests.
 * 
 */
class FakeBot extends Bot {

	/**
	 * @var string last message to main chat
	 */
	public $last_main_msg = '';

	/**
	 * @var string last message to alarm chat
	 */
	public $last_alarm_msg = '';

	/**
	 * @var array Bot options
	 */
	public $options = [];

	/**
	 * @var mixed Ok API response
	 */
	public $okResponse = ['ok' => 1];


	/**
	 * 
	 * Constructor
	 * 
	 * @param int T-bot id (in DB identity)
	 * @param int T-bot host id (web hosting identity)
	 * @param string MAX bot API token
	 * @param string Telergam admin/alarm chat id (to manage and notify on error)
	 */
	public function __construct($bot_id = 0, $bot_host_id = 0, $api_token = '00:AA', $admin_chat_id = '00') {

		$this->bot_id = $bot_id;
		$this->bot_host_id = $bot_host_id;
		$this->admin_chat_id = $admin_chat_id;

		// split token into id and key
		list($this->api_id, $this->api_key) = explode(':', $api_token, 2);

		// prepare API request uri and secret_token
		$this->api_path = '/';
		$this->main_chats_ids = ['11'];
	}
	
	/**
	 * 
	 * Send an action to MAX bot
	 * 
	 * @return mixed API response
	 */
	public function send($action, $postfields = null, $do_log = true) {
		return $this->okResponse;
	}
	
	/**
	 * 
	 * Get and save updates from MAX bot
	 * 
	 * @return mixed API response
	 */
	public function getUpdates($new_only = true) {
		return $this->okResponse;
	}
	
	/**
	 * 
	 * Prepare a MAX bot webhook URL
	 * 
	 * @return string webhook URL
	 */
	public function getWebhookUrl() {
		return '/';
	}
	
	/**
	 * 
	 * Send a message to MAX chat
	 * 
	 * @return bool status of the operation
	 */
	public function sendMessage($chat_id, $text, $parse_mode = '', $do_log = true, $more_fields = null) {
		$this->last_main_msg = $text;
		return 99;
	}
	
	/**
	 * Send a text message to the alarm MAX chat
	 */
	public function sendToAlarmChat($message, $parse_mode = '', $do_log = false, $more_fields = null) {
		$this->last_alarm_msg = $message;
		return $this->sendMessage('00', $message, $parse_mode, false, $more_fields);
	}
    

	/**
	 * Get the bot option
	 */
	public function getOption($key, $default = false) {
		return $this->options[$key] ?? $default;
	}
	
	/**
	 * Set the bot option
	 */
	public function setOption($key, $value) {
		return true;
	}
	
	/**
	 * Get OZON client id and api key as array
	 */
	public function getOZONToken() {
		return ['', ''];
	}
}
