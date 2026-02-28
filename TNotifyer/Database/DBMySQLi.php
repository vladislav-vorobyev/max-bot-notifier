<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Database;

use TNotifyer\Providers\Log;

/**
 * 
 * Single object to provide base communication with database.
 * 
 * Requires defined constants: DB_HOST, DB_USER, DB_PASS, DB_DB
 * 
 */
class DBMySQLi {

	/**
	 * @var mixed DB handler
	 */
	protected static $mysqli;
	
	/**
	 * @var bool silent on error
	 */
	protected static $silent_on_error;

	/**
	 * @var string last error message
	 */
	protected static $last_error_message;


	/**
	 * Report about request error
	 * 
	 * @param mixed error message (get error from mysql by default)
	 */
	public static function log_result_error($msg = false) {
		if (!$msg) $msg = 'Ошибка запроса: ' . self::$mysqli->error;
		self::$last_error_message = $msg;
		if (!self::$silent_on_error)
			echo $msg;
		// send error to bot
		Log::alarm($msg);
	}

	/**
	 * Initialize connection to database
	 * 
	 * @param bool silent on error (false by default)
	 */
	public static function init($silent_on_error = false) {
		self::$last_error_message = false;
		self::$silent_on_error = $silent_on_error;
		if (!self::$silent_on_error)
			mysqli_report(MYSQLI_REPORT_ERROR);
		
		// connect to MySQL database
		self::$mysqli = new \mysqli( DB_HOST, DB_USER, DB_PASS, DB_DB );

		if (mysqli_connect_errno()) {
			self::$last_error_message = $msg = sprintf( "Не удалось подключиться: %s", mysqli_connect_error() );
			if (!self::$silent_on_error) echo $msg;
			Log::alarm($msg); // send error to bot
			return false;
		}
		
		return self::$mysqli;
	}

	/**
	 * Make a query
	 * 
	 * @param string sql to run
	 */
	public static function query($sql) {
		$result = self::$mysqli->query($sql);
		// report error
		if ($result === false)
			self::log_result_error();
		// return result
		return $result;
	}

	/**
	 * Run query and fetch result (via fetch_all)
	 * 
	 * @param string sql to run
	 */
	public static function fetch_all($sql) {
		if ($res = self::$mysqli->query($sql)) {
			return $res->fetch_all( MYSQLI_ASSOC );
		}
		return false;
	}

	/**
	 * Run query and fetch result row (via fetch_row)
	 * 
	 * @param string sql to run
	 */
	public static function fetch_row($sql) {
		if ($res = self::$mysqli->query($sql)) {
			return $res->fetch_row();
		}
		return false;
	}


	/**
	 * Bind params and execute SQL
	 * 
	 * @param bool return result
	 * @param string sql to run
	 * @param string params bindings string
	 * @param mixed params to use
	 */
	protected static function _bind_and_execute_sql($is_result, $sql, $bindings, ...$args) {
		// prepare sql and execute
		if ($stmt = self::$mysqli->prepare($sql)) {
			$stmt->bind_param( $bindings, ...$args );
			if ($stmt->execute()) {
				if ($is_result) {
					$result = self::_get_result($stmt);
				} else {
					$result = $stmt->affected_rows;
				}
            } else {
                $result = false;
                self::log_result_error('Ошибка stmt: ' . $stmt->error); // report error
            }
			$stmt->close();
		} else {
            $result = false;
			self::log_result_error(); // report error
		}
		// return result
		return $result;
	}

	/**
	 * Get result from mysqli_stmt class object
	 * 
	 * @param mysqli_stmt result handler
	 */
	protected static function _get_result($stmt) {
		$result = [];
		// prepare array to bind
		$meta = $stmt->result_metadata();
		while ($field = $meta->fetch_field()) {
			$params[] = &$row[$field->name];
		}
		// bind var for fetch
		call_user_func_array(array($stmt, 'bind_result'), $params);
		// fetch all data
		while ($stmt->fetch()) {
			foreach($row as $key => $val) {
				$c[$key] = $val;
			}
			$result[] = $c;
		}
		return $result;
	}

	/**
	 * Get result by SQL
	 * 
	 * @param string sql to run
	 * @param string params bindings string
	 * @param mixed params to use
	 */
	public static function result_by_sql($sql, $bindings, ...$args) {
		return self::_bind_and_execute_sql(true, $sql, $bindings, ...$args);
	}

	/**
	 * Execute SQL
	 * 
	 * @param string sql to run
	 * @param string params bindings string
	 * @param mixed params to use
	 */
	public static function execute_sql($sql, $bindings, ...$args) {
		return self::_bind_and_execute_sql(false, $sql, $bindings, ...$args);
	}


	/**
	 * To check is connection initialized
	 */
	public static function is_ready() {
		return isset(self::$mysqli);
	}

	/**
	 * Get last error message
	 */
	public static function last_error_message() {
		return self::$last_error_message;
	}

	/**
	 * Check has been there an error
	 */
	public static function last_error() {
		return !(self::$last_error_message === false);
	}

}
