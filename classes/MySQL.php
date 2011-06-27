<?php

class MySQL {

	
	private $host;
	private $user;
	private $password;
	private $db;
	private $query_log;
	private $memcache;
	private $useMemcache=false;
	
	private $instance=false;

	function MySQL($host, $user, $password, $db, $useMemcache=false, $memcache=false) {
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->db = $db;
		$this->query_log = array();
		
		if($useMemcache) {
			$this->useMemcache = true;
			$this->memcache = $memcache;
		}
	}

	private function connect() {
		$this->instance = new mysqli($this->host,$this->user,$this->password,$this->db);
		if($this->instance->connect_error) die($this->instance->connect_error);
		$this->query("set character set utf8");
	}
	
	public function query($sql,$memcache=false,$memcache_timeout=false) {
		/**
		 *   $sql =>
		 *   	Das SQL-Statement
		 *   $memcache =>
		 *   	Der Name, nach dem im Memcache geschaut werden soll. Existiert ein Eintrag mit
		 *   	diesem Namen, wird sofort das Ergebnis zurückgegeben, ansonsten wird das
		 *   	SQL-Statement ausgeführt, das Ergebnis im Memcache gespeichert und das Resultat
		 *   	zurückgegeben
		 *   $memcache_timeout =>
		 *   	Nach x Sekunden, läuft der Eintrag im Memcache automatisch ab, was den gleichen
		 *   	Effekt erzielt, als würde man es mit $this->MySQL->memcacheDelete() löschen
		**/
		
		if(!$this->useMemcache) $memcache = false;
		
		$start	= libSystem::getMicrotime();
		
		$memcache_check = false;
		if($memcache) {
			$result = $this->memcache->get($memcache,null,true);
			if($result) $memcache_check = true;
		}
		
		if(!$memcache_check) {
			if(!$this->instance) {
				$this->connect();
			}
			$result = $this->instance->query($sql);
		}
		
		$end = libSystem::getMicrotime();
		
		if(!$result) {
			if($this->instance->error == 'MySQL server has gone away') {
				$this->connect();
				return $this->query($sql, $memcache, $memcache_timeout);
			} else {
				die("MySQL Error: ".$this->instance->error."\n");
			}
		}
		
		/*
		$log = array();
		$log['query']	= $sql;
		$log['time']	= number_format(round(($end-$start)*1000,4),4);
		if($memcache) {
			if($memcache_check) $log['memcache'] = 'get';
			else $log['memcache'] = 'add';
		} else $log['memcache'] = false;
		
		array_push($this->query_log,$log);
		*/
		
		if($memcache_check) return $result;
		
		if(strtoupper(substr(trim($sql),0,6)) == "SELECT") {
			$return_array           = array();
			$return_array['result'] = array();
			$return_array['count']  = $result->num_rows;
			
			while($row = $result->fetch_assoc()) {
				array_push($return_array['result'],$row);
			}
			
			if($memcache) $this->memcache->add($memcache,$return_array,false,$memcache_timeout?$memcache_timeout:0,true);
			
			return $return_array;
		} elseif(strtoupper(substr(trim($sql),0,6)) == "INSERT") {
			return $this->instance->insert_id;
		} elseif(strtoupper(substr(trim($sql),0,6)) == "UPDATE") {
			return $this->instance->affected_rows;
		} else {
			return true;
		}
	}
	
	public function fetchColumn($sql,$memcache=null,$memcache_timeout=null) {
		$res = $this->query($sql,$memcache,$memcache_timeout);
		if(!$res['count']) return false;
		
	return current($res['result'][0]);
	}
	
	public function fetchRow($sql,$memcache=null,$memcache_timeout=null) {
		$res = $this->query($sql,$memcache,$memcache_timeout);
		if(!$res['count']) return false;
		
	return $res['result'][0];
	}
	
	
	function debugOutput() {
		/*
			Wird nur ausgeführt, wenn in config/core.conf debug=1 ist
		*/
		echo '<table cellspacing="5" width="990" style="border:1px solid gray;">';
		echo '<tr><th colspan="3" align="center">MySQL</th></tr>';
		echo '<tr><th>Num</th><th width="800">Query</th><th>Time</th></tr>';
		$c = 1;
		$sum = 0;
		foreach($this->query_log as $query) {
			$sum+=$query['time'];
			echo '<tr>';
			echo '<td align="center">'.$c++.'</td>';
			echo '<td>'.htmlspecialchars($query['query']).'</td>';
			echo '<td align="right">'.$query['time'].' ms';
			if($query['memcache']) {
				switch($query['memcache']) {
					case 'add':
						echo ' mca';
						break;
					case 'get':
						echo ' mcg';
						break;
				}
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '<tr><td colspan="3">Total query time: <strong>'.$sum.' ms</strong></td></tr>';
		echo '</table>';
		echo '<br />';
	}
	
}

?>
