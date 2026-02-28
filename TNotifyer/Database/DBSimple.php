<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Database;

use TNotifyer\Engine\Storage;

/**
 * 
 * Single object to provide base communication with database via stored object.
 * 
 */
class DBSimple {

	/**
	 * Report about request error
	 * 
	 * @param mixed error message (get error from mysql by default)
	 */
	public static function log_result_error($msg = false) {
		Storage::get('DBSimple')->log_result_error($msg);
	}

	/**
	 * Initialize connection to database
	 * 
	 * @param bool silent on error (false by default)
	 */
	public static function init($silent_on_error = false) {
		return Storage::get('DBSimple')->init($silent_on_error);
	}

	/**
	 * Make a query
	 * 
	 * @param string sql to run
	 */
	public static function query($sql) {
		return Storage::get('DBSimple')->query($sql);
	}

	/**
	 * Run query and fetch result (via fetch_all)
	 * 
	 * @param string sql to run
	 */
	public static function fetch_all($sql) {
		return Storage::get('DBSimple')->fetch_all($sql);
	}

	/**
	 * Run query and fetch result row (via fetch_row)
	 * 
	 * @param string sql to run
	 */
	public static function fetch_row($sql) {
		return Storage::get('DBSimple')->fetch_row($sql);
	}

	/**
	 * Get result by SQL
	 * 
	 * @param string sql to run
	 * @param string params bindings string
	 * @param mixed params to use
	 */
	public static function result_by_sql($sql, $bindings, ...$args) {
		return Storage::get('DBSimple')->result_by_sql($sql, $bindings, ...$args);
	}

	/**
	 * Execute SQL
	 * 
	 * @param string sql to run
	 * @param string params bindings string
	 * @param mixed params to use
	 */
	public static function execute_sql($sql, $bindings, ...$args) {
		return Storage::get('DBSimple')->execute_sql($sql, $bindings, ...$args);
	}


	/**
	 * To check is connection initialized
	 */
	public static function is_ready() {
		return Storage::get('DBSimple')->is_ready();
	}

	/**
	 * Get last error message
	 */
	public static function last_error_message() {
		return Storage::get('DBSimple')->last_error_message();
	}

	/**
	 * Check has been there an error
	 */
	public static function last_error() {
		return Storage::get('DBSimple')->last_error();
	}

}
