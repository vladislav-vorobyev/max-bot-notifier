<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Database;

use function strlen;
use function intval;
use TNotifyer\Engine\Storage;
use TNotifyer\Providers\Log;

/**
 * 
 * Single object to provide communication with database.
 * 
 */
class DB extends DBCommon {
	/**
	 * Max log code length
	 */
	public const LOG_CODE_LENGTH = 5000;
	

	/**
	 * Prepare JSON value to save in database
	 * 
	 * @param mixed data
	 * 
	 * @return string|bool|null json string
	 */
	public static function _json($data) {
		if (null === $data) return null;
		$json = json_encode($data);
		// make a limit
		if (strlen($json) > self::LOG_CODE_LENGTH) {
			$json = json_encode( mb_strimwidth($json, 0, self::LOG_CODE_LENGTH - 10, '...') );
		}
		return $json;
	}
	

	/**
	 * Store to log table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param string type
	 * @param string message
	 * @param mixed data (optional)
	 * 
	 * @return int|bool action result
	 */
	public static function log($bot_id, $type, $message, $data = null) {
		if (!self::is_ready()) return false; // return if not initialized
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		$data = self::_json($data);
		return self::insert('a_log', compact('bot_id', 'type', 'message', 'data'));
	}


	/**
	 * Store to bot_log table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param string action
	 * @param mixed request
	 * @param mixed response
	 * 
	 * @return int|bool action result
	 */
	public static function insert_bot_log($bot_id, $action, $request, $response) {
		if (!self::is_ready()) return false; // return if not initialized
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		$request = self::_json($request);
		$response = self::_json($response);
		return self::insert( 'bot_log', compact('bot_id', 'action', 'request', 'response'));
	}

	/**
	 * Store to bot_updates table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param array updates
	 * 
	 * @return int stored count
	 */
	public static function insert_bot_updates($bot_id, $updates) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		$sql = 'INSERT IGNORE INTO bot_updates (bot_id, update_id, cmd, value) VALUES (?,?,?,?)';
		$count = 0;
		foreach ($updates as &$update) {
			// prepare values
			$update_id = intval($update['update_id']);
			$keys = array_diff( array_keys($update), ['update_id'] );
			$cmd = array_shift($keys);
			$value = json_encode($update);
			// execute
			$result = self::execute_sql($sql, 'iiss', $bot_id, $update_id, $cmd, $value);
			if ($result === false) {
				return false;
			}
			// count
			$count += $result;
		}
		// return count
		return $count;
	}

	/**
	 * Store to postings table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param string type
	 * @param string posting number
	 * @param string status value
	 * @param mixed posting data
	 * 
	 * @return int|bool action result
	 */
	public static function insert_posting($bot_id, $type, $posting_number, $status, $posting) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		$json = json_encode($posting);
		$sql = 'INSERT IGNORE INTO postings (bot_id, type, posting_number, status, data) VALUES (?,?,?,?,?)';
		// execute
		return self::execute_sql($sql, 'issss', $bot_id, $type, $posting_number, $status, $json);
	}

	/**
	 * Update/insert into activity table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param string type
	 * @param string article
	 * @param string status value
	 * @param mixed data (optional)
	 * 
	 * @return int/bool -1/1 - success update/insert, 0 - up to date, false - operation fail
	 */
	public static function save_activity($bot_id, $type, $article, $status, $data = null) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		$json = is_null($data)? null : json_encode($data);
		return self::save('activity', ['bot_id' => $bot_id, 'type' => $type, 'article' => $article], ['status' => $status, 'data' => $json]);
	}

	/**
	 * Store to posting status table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param string type
	 * @param string posting number
	 * @param string status value
	 * @param string sup_status value
	 * @param string order text (optional)
	 * @param mixed message_id (optional)
	 * @param string created (optional)
	 */
	public static function save_posting_status($bot_id, $type, $posting_number, $status, $sup_status, $order_text = '', $message_id = [], $created = '') {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		if (empty($created)) {
			$sql = 'INSERT INTO posting_status (bot_id, type, posting_number, status, sup_status, order_text, message_id) VALUES (?,?,?,?,?,?,?)'
			. ' ON DUPLICATE KEY UPDATE `status`=?, `sup_status`=?, `updated`=NOW()';
			return self::execute_sql($sql, 'issssssss', $bot_id, $type, $posting_number, $status, $sup_status, $order_text, self::_json($message_id), $status, $sup_status);
		} else {
			$sql = 'INSERT INTO posting_status (bot_id, type, posting_number, status, sup_status, order_text, message_id, created) VALUES (?,?,?,?,?,?,?,?)'
			. ' ON DUPLICATE KEY UPDATE `status`=?, `sup_status`=?, `updated`=NOW()';
			return self::execute_sql($sql, 'isssssssss', $bot_id, $type, $posting_number, $status, $sup_status, $order_text, self::_json($message_id), $created, $status, $sup_status);
		}
	}


	/**
	 * Get last from postings table
	 * 
	 * @param int limit to get
	 * @param int|array|null bot internal id (optional, -1 = get Bot Id from Storage)
	 * @param string|array|null type (optional)
	 * @param string|array|null posting number (optional)
	 * @param string|array|null posting status (optional)
	 */
	public static function get_last_postings($limit, $bot_id = null, $type = null, $posting_number = null, $status = null) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		// execute
		return self::select('postings', [
			'where' => ['bot_id' => $bot_id, 'type' => $type, 'posting_number' => $posting_number, 'status' => $status],
			'orderby' => 'id DESC',
			'limit' => $limit
		]);
	}

	/**
	 * Get last from a_log table
	 * 
	 * @param int limit to get
	 * @param int|array|null bot internal id (optional, -1 = get Bot Id from Storage)
	 * @param string|array|null type (optional)
	 */
	public static function get_last_log($limit, $bot_id = null, $type = null) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		// execute
		return self::select('a_log', [
			'where' => ['bot_id' => $bot_id, 'type' => $type], 'orderby' => 'id DESC', 'limit' => $limit
		]);
	}

	/**
	 * Get last from bot_updates table
	 * 
	 * @param int limit to get
	 * @param int|array|null bot internal id (optional, -1 = get Bot Id from Storage)
	 */
	public static function get_last_updates($limit, $bot_id = null) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		// execute
		return self::select('bot_updates', [
			'where' => ['bot_id' => $bot_id], 'orderby' => 'created DESC, update_id DESC', 'limit' => $limit
		]);
	}

	/**
	 * Get from posting status table
	 * 
	 * @param int limit to get
	 * @param int|array|null bot internal id (optional, -1 = get Bot Id from Storage)
	 * @param string|array|null type (optional)
	 * @param string|array|null posting number (optional)
	 * @param string|array|null posting status (optional)
	 */
	public static function get_posting_status($limit, $bot_id = null, $type = null, $posting_number = null, $status = null) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		// execute
		return self::select('posting_status', [
			'where' => ['bot_id' => $bot_id, 'type' => $type, 'posting_number' => $posting_number, 'status' => $status],
			'orderby' => 'created DESC',
			'limit' => $limit
		]);
	}

	/**
	 * Determine a time from last record of type
	 * 
	 * @param int|array|null bot internal id (optional, -1 = get Bot Id from Storage)
	 * @param string|array|null type (optional)
	 * @param string|array|null message (optional)
	 * 
	 * @return array ['sec', 'created'] time in seconds and created datetime
	 */
	public static function get_last_log_time($bot_id = null, $type = null, $message = null) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		// execute
		return self::select('a_log', [
			'columns' => 'TIME_TO_SEC( TIMEDIFF( NOW(), created ) ) AS sec, created',
			'where' => ['bot_id' => $bot_id, 'type' => $type, 'message' => $message],
			'orderby' => 'id DESC',
			'limit' => 1,
		]);
	}

	/**
	 * Determine a days period to earliest record of posting_status
	 * 
	 * @param int|array|null bot internal id (optional, -1 = get Bot Id from Storage)
	 * @param string|array|null type (optional)
	 * @param string|array|null posting number (optional)
	 * @param string|array|null posting status (optional)
	 * 
	 * @return array ['days', 'created'] days period and created datetime
	 */
	public static function get_days_of_status($bot_id = null, $type = null, $posting_number = null, $status = null) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		// execute
		return self::select('posting_status', [
			'columns' => 'DATEDIFF( NOW(), created ) AS days, created',
			'where' => ['bot_id' => $bot_id, 'type' => $type, 'posting_number' => $posting_number, 'status' => $status],
			'orderby' => 'created ASC',
			'limit' => 1,
		]);
	}


	/**
	 * Store to bot chats table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param string chat id
	 * @param string type
	 * @param string chat title
	 */
	public static function save_bot_chats($bot_id, $chat_id, $type, $title) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		return self::save('bot_chats', ['bot_id' => $bot_id, 'chat_id' => $chat_id, 'type' => $type], ['title' => $title]);
	}

	/**
	 * Remove from bot chats table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param string chat id
	 */
	public static function remove_bot_chats($bot_id, $chat_id) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		$sql = 'DELETE FROM bot_chats WHERE bot_id = ? AND chat_id = ?';
		// execute
		return self::execute_sql($sql, 'is', $bot_id, $chat_id);
	}

	/**
	 * Get from bot chats table
	 * 
	 * @param int|array|null bot internal id (-1 = get Bot Id from Storage)
	 * @param string|array|null type (optional)
	 */
	public static function get_bot_chats($bot_id, $type = null) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		return self::select('bot_chats', ['where' => ['bot_id' => $bot_id, 'type' => $type]]);
	}


	/**
	 * Store to bot options table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param string option key
	 * @param string option value
	 * 
	 * @return int action result
	 */
	public static function save_bot_option($bot_id, $key, $value) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		return self::save('bot_options', ['bot_id' => $bot_id, '`key`' => $key], ['`value`' => $value]);
	}

	/**
	 * Get from bot options table
	 * 
	 * @param int bot internal id (-1 = get Bot Id from Storage)
	 * @param string option key
	 * 
	 * @return array option row or empty array
	 */
	public static function get_bot_option($bot_id, $key) {
		if ($bot_id === -1) $bot_id = Storage::get('Bot')->getId();
		$res = self::select('bot_options', ['where' => ['bot_id' => $bot_id, '`key`' => $key]]);
		// return first row if found
		return (!empty($res))? $res[0] : [];
	}

}
