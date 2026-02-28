<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

use TNotifyer\Database\DB;
use TNotifyer\Engine\Storage;
use TNotifyer\Providers\Log;
use TNotifyer\Exceptions\InternalException;
use TNotifyer\Exceptions\ExternalRequestException;

/**
 * 
 * Provides interface with MAX bot.
 * 
 */
class MAXBot {

    /**
     * MAX API URL
     */
	public const MAX_API_URL = 'https://api.telegram.org/bot';
	
    /**
     * Max length of code block to send in alarm message
     */
	public const ALARM_CODE_LENGTH = 500;
	
    /**
     * @var string MAX bot API id
     */
	protected $api_id;
	
    /**
     * @var string MAX bot API key
     */
	protected $api_key;
	
    /**
     * @var string Full MAX API path for bot
     */
	protected $api_path;
	
    /**
     * @var string MAX bot webhook secret token
     */
	protected $api_secret_token;
	
    /**
     * @var mixed MAX bot user (got from API via getMe)
     */
	protected $info;
	
    /**
     * @var int T-bot id (in DB identity)
     */
	protected $bot_id;
	
    /**
     * @var int T-bot host id (in chat identity)
     */
	protected $bot_host_id;
	
    /**
     * @var string MAX alarm chat id (to notify on error)
     */
	protected $admin_chat_id;

    /**
     * @var array MAX main chats ids
     */
	protected $main_chats_ids;
	

	/**
	 * 
	 * Constructor
	 * 
	 * @param int T-bot id (in DB identity)
	 * @param int T-bot host id (web hosting identity)
	 * @param string MAX bot API token
	 * @param string MAX admin/alarm chat id (to manage and notify on error)
	 */
	public function __construct($bot_id, $bot_host_id, $api_token, $admin_chat_id) {

		$this->bot_id = $bot_id;
		$this->bot_host_id = $bot_host_id;
		$this->admin_chat_id = $admin_chat_id;

		// split token into id and key
		$parsed_token = explode(':', $api_token, 2);
		if (empty($parsed_token[1]))
			throw new InternalException('Wrong MAX Bot token structure!');
		[$this->api_id, $this->api_key] = $parsed_token;

		// prepare API request uri and secret_token
		$this->api_path = self::MAX_API_URL . $api_token . '/';
		$this->api_secret_token = substr($this->api_key, 0, 20);

		// testing the token and get bot info (no log this action)
		$resp = $this->send('getMe', null, false);
		if (self::isOK($resp) && isset($resp['result']))
			$this->info = $resp['result'];
		else
			throw new InternalException('Wrong MAX Bot token!');

		// get main chats list
		$this->main_chats_ids = [];
		foreach (DB::get_bot_chats($this->bot_id, 'main') as $chat) {
			$this->main_chats_ids[] = $chat['chat_id'];
		}
	}
	
	/**
	 * API Id getter
	 * 
	 * @return string bot API id
	 */
	public function getAPIId() {
		return $this->api_id;
	}
	
	/**
	 * Id getter
	 * 
	 * @return int bot id
	 */
	public function getId() {
		return $this->bot_id;
	}
	
	/**
	 * Host id getter
	 * 
	 * @return int host id
	 */
	public function getHostId() {
		return $this->bot_host_id;
	}
	
	/**
	 * Admin chat id getter
	 * 
	 * @return string admin chat id
	 */
	public function getAdminChatId() {
		return $this->admin_chat_id;
	}
	
	/**
	 * Main chats ids getter
	 * 
	 * @return array chats ids
	 */
	public function getMainChatsIds() {
		return $this->main_chats_ids;
	}
	
	/**
	 * Check an API response for good status
	 * 
	 * @param mixed API response
	 * 
	 * @return bool status
	 */
	public static function isOK($response) {
		return (isset($response['ok']) && $response['ok'] == 1 && isset($response['result']))? true : false;
	}
	
	/**
	 * 
	 * Send a request to MAX API
	 * 
	 * @param string API method
	 * @param mixed request data (optional)
	 * @param bool store an action to log (true by default)
	 * 
	 * @return mixed API response
	 */
	public function send($action, $postfields = null, $do_log = true) {
		// do log
		if ($do_log)
			Log::put('tbot-send', $action, $postfields);

		// do request
		$response = Storage::get('CURL')->post(
			$this->api_path . $action,
			['Content-Type: application/json'],
			is_null($postfields)? '' : json_encode($postfields)
		);

		// check response
		if ($do_log) {
			if (empty($response)) {
				Log::put('error', "Empty response on $action");
			} elseif (!self::isOK($response)) {
				Log::put('error', "No OK response on $action", $response);
			}
			DB::insert_bot_log($this->bot_id, $action, $postfields, $response);
		}
		
		return $response;
	}
	
	/**
	 * 
	 * Get and save updates from MAX bot
	 * 
	 * @param bool use an offset from DB in request to get a new only (true by default)
	 * 
	 * @return mixed API response
	 */
	public function getUpdates($new_only = true) {
		// MAX API action
		$action = 'getUpdates';

		// filter
		if ($new_only) {
			$sql = 'SELECT max(update_id)+1 FROM bot_updates WHERE bot_id=' . $this->bot_id;
			$update_id = ($result = DB::fetch_row($sql))? $result[0] : 1;
			if ($update_id)
				$action .= '?offset=' . $update_id;
		}

		// make request (no log this action)
		$response = $this->send($action, null, false);

		// save
		if ($response && isset($response['result'])) {
			DB::insert_bot_updates($this->bot_id, $response['result']);
		}
		
		return $response;
	}
	
	/**
	 * 
	 * Get and check updates from MAX bot
	 * 
	 * @return mixed API response
	 */
	public function checkUpdates() {
		// make request
		$response = $this->getUpdates();

		// check
		if ($response && isset($response['result'])) {
			foreach ($response['result'] as &$update) {
				$this->checkUpdate($update);
			}
		}

		return $response;
	}
	
	/**
	 * 
	 * Check an update from MAX bot.
	 * Add/remove a chats to/from main list.
	 * 
	 * @param mixed incoming API update
	 */
	public function checkUpdate($update) {
		// do not throw exception on bot update
		try {
			// inspecting my_chat_member update
			$r_status = &$update['my_chat_member']['new_chat_member']['status'];
			$r_user_id = &$update['my_chat_member']['new_chat_member']['user']['id'];
			$r_chat_id = &$update['my_chat_member']['chat']['id'];
			$r_chat_title = &$update['my_chat_member']['chat']['title'];

			// if this bot added/removed like member to/from chat
			if ($this->api_id == ($r_user_id ?? '') && isset($r_chat_id)) {
				if ('member' == ($r_status ?? '')) {
					// store chat into main list
					DB::save_bot_chats($this->bot_id, $r_chat_id, 'main', $r_chat_title);
					// notify about adding
					$this->sendToAlarmChat("Привязан новый чат для оповещений: " . ($r_chat_title ?? ''));
				}
				if ('left' == ($r_status ?? '')) {
					// remove chat from main list
					DB::remove_bot_chats($this->bot_id, $r_chat_id);
				}
			}
			
			unset($r_status, $r_user_id, $r_chat_id, $r_chat_title);
		} catch(\Exception $e) {
			Log::put('error', "Fail my_chat_member update check. " . $e->getMessage());
		}
	}
	
	/**
	 * 
	 * MAX bot webhook handler
	 * 
	 * @return mixed response to API
	 */
	public function webhook() {
		// check request
		$request = Storage::get('Request');
		$r_secret_token = &$request->headers['X-MAX-Bot-Api-Secret-Token'];
		if (!isset($r_secret_token) || $r_secret_token != $this->api_secret_token)
			throw new InternalException('Forbidden!');

		// get request data
		$update = $request->post;

		if ($update && isset($update['update_id'])) {
			// save
			DB::insert_bot_updates($this->bot_id, [$update]);

			// check
			$this->checkUpdate($update);

			return true;
		}
		
		return false;
	}
	
	/**
	 * 
	 * Prepare a MAX bot webhook URL
	 * 
	 * @return string webhook URL
	 */
	public function getWebhookUrl() {
		return Storage::get('Request')->root_uri . 'webhook';
	}
	
	/**
	 * 
	 * Set a webhook to MAX bot
	 * 
	 * @return mixed response from API
	 */
	public function setWebhook() {
		// MAX API action and request data
		$action = 'setWebhook';
		$postfields = [
			'url' => $this->getWebhookUrl(),
			'secret_token' => $this->api_secret_token
		];

		// log
		Log::put('tbot-send', $action);

		// make request to MAX API without log for security reason
		$response = $this->send($action, $postfields, false);

		// bot log
		unset($postfields['secret_token']);
		DB::insert_bot_log($this->bot_id, $action, $postfields, $response);

		return $response;
	}
	
	/**
	 * 
	 * Remove a webhook from MAX bot
	 * 
	 * @return mixed response from API
	 */
	public function removeWebhook() {
		// make request to MAX API
		return $this->send('deleteWebhook');
	}
	
	/**
	 * 
	 * Get chat information from MAX
	 * 
	 * @param string chat id
	 * 
	 * @return mixed response from API
	 */
	public function getChat($chat_id) {
		// make request to MAX API
		return $this->send('getChat', ['chat_id' => $chat_id]);
	}
	
	/**
	 * 
	 * Get chat title/name
	 * 
	 * @param string chat id
	 * 
	 * @return string title/name
	 */
	public function getChatTitle($chat_id) {
		$res = $this->getChat($chat_id);
		if (!self::isOK($res) || empty($res['result'])) {
			Log::put('error', 'Wrong response from getChat', $res);
			return '';
		}
		$chat = $res['result'] ?? [];
		$type = $chat['type'] ?? '';
		$title = $chat['title'] ?? $chat['username'] ?? '';
		$name = trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''));
		return $title . (!empty($name)? " / $name" : '') . " ($type)";
	}
	
	/**
	 * 
	 * Send a message to MAX chat
	 * 
	 * @param string chat id
	 * @param string message
	 * @param string parse mode of the message (optional)
	 * @param bool store an action to log (true by default)
	 * @param array more postfields to send (optional)
	 * 
	 * @return int|bool message id or false
	 */
	public function sendMessage($chat_id, $text, $parse_mode = '', $do_log = true, $more_fields = null) {
		// MAX API action and request data
		$action = 'sendMessage';
		$postfields = [
			'chat_id' => $chat_id,
			'text' => $text
		];
		if (!empty($parse_mode))
			$postfields['parse_mode'] = $parse_mode;
		if (!empty($more_fields))
			$postfields = array_merge($postfields, $more_fields);

		// make request to MAX API
		$response = $this->send($action, $postfields, $do_log);
		Log::debug(print_r($response, true));
		
		return self::isOK($response)? intval($response['result']['message_id'] ?? 0) : false;
	}
	
	/**
	 * Send a text message to the main MAX chats
	 * 
	 * @param string message
	 * @param string parse mode of the message (optional)
	 * @param bool store an action to log (true by default)
	 * @param array more postfields to send (optional)
	 * 
	 * @return array ['chat_id' => 'message_id', ...]
	 */
	public function sendToMainChats($text, $parse_mode = '', $do_log = true, $more_fields = []) {
		$result = [];
		foreach ($this->main_chats_ids as $i => $chat_id) {
			$more = $more_fields[$i] ?? $more_fields[0] ?? $more_fields ?? null;
			if ($message_id = $this->sendMessage($chat_id, $text, $parse_mode, $do_log, $more))
				$result[$chat_id] = $message_id;
		}
		return $result;
	}
	
	/**
	 * Send a reply message to the main MAX chats
	 * 
	 * @param array message ids to reply
	 * @param string message
	 * @param string parse mode of the message (optional)
	 * @param bool store an action to log (true by default)
	 * 
	 * @return array ['chat_id' => 'message_id', ...]
	 */
	public function replyToMainChats($reply_ids, $text, $parse_mode = '', $do_log = true) {
		$result = [];
		foreach ($this->main_chats_ids as $i => $chat_id) {
			$more = !empty($reply_ids[$chat_id])? ['reply_parameters' => ['message_id' => $reply_ids[$chat_id]]] : null;
			if ($message_id = $this->sendMessage($chat_id, $text, $parse_mode, $do_log, $more))
				$result[$chat_id] = $message_id;
		}
		return $result;
	}
	
	/**
	 * Send a text message to the alarm MAX chat
	 * 
	 * @param string message
	 * @param string parse mode of the message (optional)
	 * @param bool store an action to log (false by default)
	 * @param array more postfields to send (optional)
	 * 
	 * @return int|bool message id or status of the operation
	 */
	public function sendToAlarmChat($message, $parse_mode = '', $do_log = false, $more_fields = null) {
		return $this->sendMessage($this->admin_chat_id, $message, $parse_mode, $do_log, $more_fields);
	}
	
	/**
	 * Send an alarm message
	 * 
	 * @param string message
	 * @param mixed data to send (optional)
	 * 
	 * @return bool status of the operation
	 */
	public function alarm($message, $data = null) {
		$status = false !== $this->sendToAlarmChat("[{$this->bot_host_id}] $message", '');
		if (null !== $data) {
			$status2 = false !== $this->sendToAlarmChat('<code>' . self::convertToJson($data) . '</code>', 'HTML');
			$status = $status && $status2;
		}
		return $status;
	}
	
	/**
	 * Prepare data as readable json for a message
	 * 
	 * @param mixed data to convert to string
	 * 
	 * @return string encoded data
	 */
	public static function convertToJson($data) {
		return mb_strimwidth(
			str_replace('    ', ' ',
				json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT)
			),
			0, self::ALARM_CODE_LENGTH, '...'
		);
	}
	
	/**
	 * Get information about the bot
	 * 
	 * @return array bot information
	 */
	public function info() {
		return $this->info;
	}
}
