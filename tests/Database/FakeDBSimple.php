<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Database;

/**
 * 
 * Provides a fake object for DBSimple to make tests.
 * 
 */
class FakeDBSimple {

	/**
	 * @var array query result
	 */
	public $query_result = 1;

	/**
	 * @var array rows result
	 */
	public $rows = [];

	/**
	 * @var string last query
	 */
	public $last_sql;

	/**
	 * @var array queries history
	 */
	public $sql_history = [];

	/**
	 * @var string last args
	 */
	public $last_args;

	/**
	 * @var array args history
	 */
	public $args_history = [];

	/**
	 * @var string last error message
	 */
	public $last_error_message;


	/**
	 * Reset rows and history
	 * 
	 * @param array new rows
	 */
	public function reset($new_rows = [], $_query_result = 1) {
		$this->rows = $new_rows;
		$this->query_result = $_query_result;
		$this->sql_history = [];
		$this->args_history = [];
		unset($this->last_sql);
		unset($this->last_args);
	}

	/**
	 * Report about request error
	 * 
	 * @param mixed error message (get error from mysql by default)
	 */
	public function log_result_error($msg = false) {
	}

	/**
	 * Initialize connection to database
	 * 
	 * @param bool silent on error (false by default)
	 */
	public function init($silent_on_error = false) {
		self::$last_error_message = false;
		return true;
	}

	/**
	 * Make a query
	 * 
	 * @param string sql to run
	 */
	public function query($sql) {
		$this->last_sql = $sql;
		$this->sql_history[] = $sql;
		$this->args_history[] = [];
		return $this->query_result;
	}

	/**
	 * Run query and fetch result (via fetch_all)
	 * 
	 * @param string sql to run
	 */
	public function fetch_all($sql) {
		$this->last_sql = $sql;
		$this->sql_history[] = $sql;
		$this->args_history[] = [];
		return $this->rows;
	}

	/**
	 * Run query and fetch result row (via fetch_row)
	 * 
	 * @param string sql to run
	 */
	public function fetch_row($sql) {
		$this->last_sql = $sql;
		$this->sql_history[] = $sql;
		$this->args_history[] = [];
		return $this->rows[0] ?? false;
	}


	/**
	 * Get result by SQL
	 * 
	 * @param string sql to run
	 * @param string params bindings string
	 * @param mixed params to use
	 */
	public function result_by_sql($sql, $bindings, ...$args) {
		$this->last_sql = $sql;
		$this->last_args = $args;
		$this->sql_history[] = $sql;
		$this->args_history[] = $args;
		return $this->rows;
	}

	/**
	 * Execute SQL
	 * 
	 * @param string sql to run
	 * @param string params bindings string
	 * @param mixed params to use
	 */
	public function execute_sql($sql, $bindings, ...$args) {
		$this->last_sql = $sql;
		$this->last_args = $args;
		$this->sql_history[] = $sql;
		$this->args_history[] = $args;
		return $this->query_result;
	}


	/**
	 * To check is connection initialized
	 */
	public function is_ready() {
		return true;
	}

	/**
	 * Get last error message
	 */
	public function last_error_message() {
		return $this->last_error_message;
	}

	/**
	 * Check has been there an error
	 */
	public function last_error() {
		return !($this->last_error_message === false);
	}

}
