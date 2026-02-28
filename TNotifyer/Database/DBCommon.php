<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Database;

use function is_string;
use function is_array;
use function count;
use function implode;

/**
 * 
 * Single object to provide common functions and base communication with database.
 * 
 */
class DBCommon extends DBSimple {
	
	/**
	 * Put row into a table
	 * 
	 * @param string table name
	 * @param array 'key' array of pairs to unique identify a row
	 * @param array 'values' array of pairs to insert or update
	 *   each like 'column' => null | $value | ['value as is'] (skipped if null)
	 * 
	 * @return int|bool -1/1 - success update/insert, 0 - up to data, false - operation fail
	 */
	public static function save($table_name, $key, $values) {

		if (self::exists($table_name, $key)) {
			// UPDATE row
			$res = self::update($table_name, $key, $values);
			return $res? -1 * $res : $res;

		} else {
			// INSERT row
			return self::insert($table_name, array_merge($key, $values));
		}
	}
	
	/**
	 * Update rows in the table
	 * 
	 * @param string table name
	 * @param array 'key' array of pairs to identify a rows
	 * @param array 'values' array of pairs to update
	 *   each like 'column' => null | $value | ['value as is'] (skipped if null)
	 * 
	 * @return int|bool updates count, false - operation fail
	 */
	public static function update($table_name, $key, $values) {
		$bind = '';
		$args = [];
		$set = [];
		$where = [];

		// prepare set sql part
		foreach ($values as $column => $value)
			// skip null values
			if (null !== $value) {
				if (is_array($value)) {
					$operation = $value[0];
				} else {
					$operation = '=?';
					// add arg and bindings string
					$args[] = $value;
					$bind .= is_string($value)? 's' : 'i';
				}
				$set[] = "{$column}{$operation}";
			}
		$set = implode(', ', $set);

		// prepare where sql part
		foreach ($key as $column => $value) {
			// add arg and bindings string
			$args[] = $value;
			$bind .= is_string($value)? 's' : 'i';
			$where[] = "{$column}=?";
		}
		$where = implode(' AND ', $where);

		// prepare sql
		$sql = "UPDATE {$table_name} SET {$set} WHERE {$where}";
		
		// execute
		return self::execute_sql($sql, $bind, ...$args);
	}
	
	/**
	 * Insert a row into the table
	 * 
	 * @param string table name
	 * @param array 'values' array of pairs to insert
	 *   each like 'column' => null | $value | ['value as is'] (skipped if null)
	 * 
	 * @return int|bool insert count, false - operation fail
	 */
	public static function insert($table_name, $values) {
		$bind = '';
		$args = [];
		$columns = [];
		$values_part = [];

		// fill columns and values part
		foreach ($values as $column => $value)
			// skip null values
			if (null !== $value) {
				$columns[] = $column;
				if (is_array($value)) {
					$values_part[] = $value[0];
				} else {
					$values_part[] = '?';
					// add arg and bindings string
					$args[] = $value;
					$bind .= is_string($value)? 's' : 'i';
				}
			}
		
		$columns = implode(', ', $columns);
		$values_part = implode(', ', $values_part);

		// prepare sql
		$sql = "INSERT INTO {$table_name} ({$columns}) VALUES ({$values_part})";
		
		// execute
		return self::execute_sql($sql, $bind, ...$args);
	}
	
	/**
	 * Check if row exists in the table
	 * 
	 * @param string table name
	 * @param array 'where' array of where cases (optional)
	 *  where case like 'column' => null | $value | ['operation', $value] | ['operation'] (skipped if null)
	 * 
	 * @return int|bool 1/0 - exists/not, false - operation fail
	 */
	public static function exists($table_name, $where) {
		return ($r = self::select($table_name, ['where' => $where])) !== false ? count($r) : false;
	}
	
	/**
	 * Get rows from a table
	 * 
	 * @param string table name
	 * @param mixed structure with next params:
	 * {
	 *   @param int 'limit' limit to get (optional)
	 *   @param string 'orderby' order by (optional)
	 *   @param array 'where' array of where cases (optional)
	 *    where case like 'column' => null | $value | ['operation', $value] | ['operation'] (skipped if null)
	 *   @param string 'columns' to select (optional, * by default)
	 * }
	 */
	public static function select($table_name, $params) {
		$bind = '';
		$args = [];

		// prepare where sql part
		$where = [];
		if (!empty($params['where'])) {
			foreach ($params['where'] as $column => $value)
				// skip null values
				if (null !== $value) {
					$add_arg = true;
					if (is_array($value)) {
						$operation = $value[0];
						if (isset($value[1])) {
							$operation .= '?';
							$value = $value[1];
						} else {
							// no arg to add
							$add_arg = false;
						}
					} else {
						// default operation
						$operation = '=?';
					}
					if ($add_arg) {
						// add arg and bindings string
						$args[] = $value;
						$bind .= is_string($value)? 's' : 'i';
					}
					$where[] = "{$column}{$operation}";
				}
		}
		$where = implode(' AND ', $where);
		if (!empty($where)) $where = " WHERE $where";

		// prepare order by sql part
		$orderby = '';
		if (!empty($params['orderby'])) {
			$orderby = ' ORDER BY ' . $params['orderby'];
		}

		// prepare limit sql part
		$limit = '';
		if (!empty($params['limit'])) {
			$limit = ' LIMIT ?';
			$bind .= 'i';
			$args[] = $params['limit'];
		}

		// prepare columns to select
		$columns = empty($params['columns'])? '*' : $params['columns'];

		// prepare sql
		$sql = "SELECT {$columns} FROM {$table_name}{$where}{$orderby}{$limit}";
		
		// execute
		return self::result_by_sql($sql, $bind, ...$args);
	}

}
