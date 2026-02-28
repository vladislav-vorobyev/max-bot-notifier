<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

use TNotifyer\Engine\Storage;
use TNotifyer\Database\DB;


/**
 * Setup debug mode if is not set yet
 */
if (!defined('DEBUG_ON')) define('DEBUG_ON', false);


/**
 * 
 * Single object to provide a storing activity log to database, make alarms and output debug information.
 * 
 */
class Log {

	/**
	 * Store a log message to DB and send message to alarm chat for error type messages
	 * 
	 * @param string message type
	 * @param string message
	 * @param mixed data (optional)
	 */
	public static function put($type, $message, $data = null) {
        $tbot = Storage::get('Bot');
        $tbot_id = empty($tbot)? 0 : $tbot->getId();

		// Send an error message to alarm chat if possible
		if ($type == 'error') {
            if (!empty($tbot)) $tbot->alarm($message, $data);
        }

		// log to DB
		DB::log($tbot_id, $type, $message, $data);
	}

	/**
	 * Send message to alarm chat
	 * 
	 * @param string message
	 * @param mixed data (optional)
	 */
	public static function alarm($message, $data = null) {
        $tbot = Storage::get('Bot');
		if (!empty($tbot)) $tbot->alarm($message, $data);
	}

	/**
	 * Output debug information
	 * 
	 * @param string message
	 */
	public static function debug($message) {
		if (DEBUG_ON) print($message . "\n");
	}
}
