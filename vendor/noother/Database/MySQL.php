<?php

namespace noother\Database;

class MySQL {

	private $host;
	private $user;
	private $password;
	private $db;
	private $port;
	private $charset;

	private $fetchModes = array('both' => MYSQLI_BOTH, 'assoc' => MYSQLI_ASSOC, 'numeric' => MYSQLI_NUM);

	private $Instance     = false;
	private $cacheEnabled = false;
	private $cache        = array();
	private $queryLogFile = false;

	public $queryCount = 0;

	private $max_allowed_packet = false;

	public function __construct($host, $user, $password, $db, $port=3306, $charset='utf8mb4') {
		$this->host     = $host;
		$this->user     = $user;
		$this->password = $password;
		$this->db       = $db;
		$this->port     = $port;
		$this->charset  = $charset;
	}

	private function connect() {
		$this->Instance = new \mysqli($this->host, $this->user, $this->password, $this->db, $this->port);
		if($this->Instance->connect_error) throw new \Exception($this->Instance->connect_error, $this->Instance->errno);
		$this->query("set character set ".$this->charset);
	}

	public function enableCache() {
		$this->cacheEnabled = true;
	}

	public function disabledCache() {
		$this->cacheEnabled = false;
	}

	public function clearCache() {
		$this->cache = array();
	}

	public function logQueries($filename) {
		$this->queryLogFile = fopen($filename, 'w');
	}

	public function query($sql, $mode='assoc') {
		if(!$this->Instance) $this->connect();

		if($this->cacheEnabled) {
			$cache_id = md5($sql);
			if(isset($this->cache[$cache_id])) return $this->cache[$cache_id];
		}

		if($this->queryLogFile !== false) {
			fputs($this->queryLogFile, ($this->queryCount+1).': '.$sql."\n");
			$start = microtime(true);
		}

		if(false === $Result = $this->Instance->query($sql)) {
			if($this->Instance->error == 'MySQL server has gone away') {
				$this->connect();
				return $this->query($sql);
			} else {
				throw new \Exception("MySQL Error: ".$this->Instance->error."\nSQL: ".$sql."\n", $this->Instance->errno);
				return false;
			}
		}

		if($this->queryLogFile !== false) {
			$time_needed = (int)((microtime(true)-$start)*1000);
			fputs($this->queryLogFile, $time_needed."ms\n\n");
		}

		preg_match('/^\s*(\w+)/', strtoupper($sql), $arr);

		switch($arr[1]) {
			case 'SELECT':
			case 'SHOW':
				if(!isset($this->fetchModes[$mode])) return false;

				$return = array();
				while($row = $Result->fetch_array($this->fetchModes[$mode])) $return[] = $row;

				if($this->cacheEnabled) $this->cache[$cache_id] = $return;
			break;

			case 'INSERT':
				$return = $this->Instance->insert_id;
			break;

			case 'UPDATE':
			case 'DELETE':
				$return = $this->Instance->affected_rows;
			break;

			default:
				$return = true;
			break;
		}

		$this->queryCount++;

	return $return;
	}

	public function fetchColumn($sql) {
		$res = $this->query($sql);
		if(empty($res)) return false;

	return current($res[0]);
	}

	public function fetchRow($sql, $mode='assoc') {
		$res = $this->query($sql, $mode);
		if(empty($res)) return false;

	return $res[0];
	}

	/**
	 * @param string|array $columns
	 * @param int|array $conditions
	 * @return string|array
	 *
	 * Returns string if $columns is a string (and not *) and array if $column is an array
	 */
	public function first($columns, string $table, $conditions=[]) {
		$results = $this->select($columns, $table, $conditions, 1);
		if(empty($results)) return null;

		return is_array($columns) || $columns == '*' ? $results[0] : $results[0][$columns];
	}

	/**
	 * @param string|array $columns
	 * @param int|array $conditions
	 */
	public function select($columns, string $table, $conditions=[], int $limit=null): array {
		$columns = (array)$columns;
		// Keep * & function as they are
		$columns = array_map(function($c) { return $c == '*' || strpos($c, '(') !== false ? $c : "`$c`"; }, $columns);
		$columns = implode(', ', $columns);

		$limit = isset($limit) ? "LIMIT $limit" : '';

		return $this->query("SELECT $columns FROM `$table` ".$this->getWhere($conditions)." $limit");
	}

	// Return insert id
	public function insert(string $table, array $data): int {
		$columns = array_keys($data);
		$columns = array_map(function($c) { return "`$c`"; }, $columns);
		$values = array_map(function($v) { return $this->escape($v); }, $data);

	return $this->query("INSERT INTO `$table` (".implode(', ', $columns).") VALUES (".implode(', ', $values).")");
	}

	// Returns number of affected rows
	public function update(string $table, array $data, $conditions=[]): int {
		$updates = array();
		foreach($data as $key => $value) {
			if(is_null($value)) $updates[] = "`$key` = NULL";
			else $updates[] = "`$key` = ".$this->escape($value);
		}

		return $this->query("UPDATE `$table` SET ".implode(', ', $updates)." ".$this->getWhere($conditions));
	}

	// Returns last insert id on INSERT or id on UPDATE
	public function save(string $table, array $data): int {
		if(isset($data['id'])) {
			$id = $data['id'];
			unset($data['id']);
			$this->update($table, $id, $data);
			return $id;
		}

	return $this->insert($table, $data);
	}

	// Returns number of affected rows
	public function delete(string $table, $conditions=[]): int {
		return $this->query("DELETE FROM `$table` ".$this->getWhere($conditions));
	}

	public function multiQuery(array $sqls): void {
		$queries = $this->splitQueries($sqls);

		foreach($queries as $sql) {
			$this->queryCount++;
			$this->Instance->multi_query($sql);
			do {
				if($this->Instance->error) throw new \Exception("MySQL Error: ".$this->Instance->error." in multi-query");

				if(false !== $Result = $this->Instance->use_result()) $Result->close();
			} while($this->Instance->more_results() && $this->Instance->next_result());
		}
	}

	public function getAffectedRows() {
		return $this->Instance->affected_rows;
	}

	private function splitQueries($sqls) {
		$max_allowed_packet = $this->getMaxAllowedPacket();

		$split = array(0 => '');
		$c = 0;
		foreach($sqls as $sql) {
			if(strlen($split[$c]) + strlen($sql) + 1 > $max_allowed_packet-$max_allowed_packet*0.1) {
				$split[++$c] = '';
			}

			$split[$c].= $sql.';';
		}

	return $split;
	}

	private function getMaxAllowedPacket() {
		if($this->max_allowed_packet) return $this->max_allowed_packet;

		$res = $this->query("show variables like 'max_allowed_packet'");
		$this->max_allowed_packet = (int)$res[0]['Value'];

	return $this->max_allowed_packet;
	}

	/**
	 * @param int|array $conditions
	 */
	private function getWhere($conditions=[]): string {
		if(empty($conditions)) return '';

		if(!is_array($conditions)) $conditions = ['id' => $conditions];

		$where = [];
		foreach($conditions as $key => $value) {
			$where[] = $this->getCompareString($key, $value);
		}

		return 'WHERE '.implode(' AND ', $where);
	}

	private function getCompareString(string $key, ?string $value): string {
		if(strstr($key, ' ')) {
			list($column, $operator) = explode(' ', $key, 2);
		} else {
			if(is_null($value)) $operator = "IS";
			else $operator = '=';

			$column = $key;
		}

		$value = $this->escape($value);

		$pair = "`$column` $operator $value";

	return $pair;
	}

	private function escape($value): string {
		if(is_array($value)) {
			$real_value = $value[0];
			$escape     = $value[1];
			if(!$escape) return $real_value;
			$value = $real_value;
		}

		if(is_null($value)) return "NULL";
		if(is_int($value) || ctype_digit($value)) return $value;
		if(is_bool($value)) return (int)$value;

	return "'".addslashes($value)."'";
	}
}
