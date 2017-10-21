<?php

	
require_once('DatabaseInterface.php');

class MySQL implements DatabaseInterface {

	private $host;
	private $user;
	private $password;
	private $db;
	private $port;
	
	private $fetchModes = array('both' => MYSQLI_BOTH, 'assoc' => MYSQLI_ASSOC, 'numeric' => MYSQLI_NUM);
	
	private $Instance=false;
	
	public $queryCount = 0;
	
	
	public function __construct($config) {
        $this->host = $config['host'];
        $this->user = $config['user'];
        $this->password = $config['pass'];
        $this->db = $config['db'];
        $this->port = $config['port'];
	}
	
	public function connect() {
		$this->Instance = new mysqli($this->host,$this->user,$this->password,$this->db);
		if($this->Instance->connect_error) die($this->Instance->connect_error);
		$this->query("set character set utf8");
	}
	
	public function query($sql, $mode='assoc') {
		$this->queryCount++;
		
		if(!$this->Instance) {
			$this->connect();
		}
		
		if(false === $Result = $this->Instance->query($sql)) {
			if($this->Instance->error == 'MySQL server has gone away') {
				$this->connect();
				return $this->query($sql);
			} else {
				trigger_error("MySQL Error: ".$this->Instance->error."\nSQL: ".$sql."\n", E_USER_WARNING);
				return false;
			}
		}
		
		preg_match('/^\s*(\w+)/', strtoupper($sql), $arr);
		
		switch($arr[1]) {
			case 'SELECT': case 'SHOW':
				if(!isset($this->fetchModes[$mode])) return false;
				
				$return = array();
				while($row = $Result->fetch_array($this->fetchModes[$mode])) $return[] = $row;
			break;
			
			case 'INSERT':
				$return = $this->Instance->insert_id;
			break;
			
			case 'UPDATE':
				$return = $this->Instance->affected_rows;
			break;
			
			default:
				$return = true;
			break;
		}
		
	return $return;
	}
	
	public function multiQuery($sql) {
		$this->queryCount++;
		
		$this->Instance->multi_query($sql);
		do {
			if($this->Instance->error) {
				trigger_error("MySQL Error: ".$this->Instance->error." in multi-query\n", E_USER_WARNING);
				return false;
			}
			
			if(false !== $Result = $this->Instance->use_result()) $Result->close();
		} while($this->Instance->more_results() && $this->Instance->next_result());
		
	return true;
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

    public function showTablesLike($name) {
        return $this->query("SHOW TABLES LIKE \"$name\";");
    }

    public function getPermanent($name, $type = 'bot', $target = 'me') {
        $statement = $this->Instance->prepare("SELECT `value` FROM `memory` WHERE `type` = ? AND `target` = ? AND `name` = ?;");
        $statement->bind_param('sss', $type, $target, $name);
        $success = $statement->execute();
        if ($success === false)
            return false;
        $value = null;
        $statement->bind_result($value);
        $statement->close();
        return $value;
    }

    public function insertPermanent($name, $value, $type = 'bot', $target = 'me') {
        $statement = $this->Instance->prepare("INSERT INTO `memory` (`name`, `type`, `target`, `value`, `created`, `modified`)
				VALUES (?,?,?,?,NOW(),NOW());");
        $statement->bind_param('ssss', $name, $type, $target, $value);
        return $statement->execute();
    }

    public function updatePermanent($name, $value, $type = 'bot', $target = 'me') {
        $statement = $this->Instance->prepare("UPDATE `memory` SET `value` = ?, `modified` = NOW() WHERE `name`=? AND `type`=? AND `target`=?;");
        $statement->bind_param('ssss', $value, $name, $type, $target);
        return $statement->execute();
    }

    public function removePermanent($name, $type = 'bot', $target = 'me') {
        $statement = $this->Instance->prepare("DELETE FROM `memory` WHERE `type` = ? AND `target` = ? AND `name` = ?;");
        $statement->bind_param('sss', $type, $target, $name);
        return $statement->execute();
    }

    public function closeConnection() {
        $this->Instance->close();
    }

}

?>
