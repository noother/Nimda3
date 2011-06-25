<?php

require_once('IRC_Target.php');


final class IRC_User extends IRC_Target {
	
	public $user     = '';
	public $host     = '';
	public $realname = '';
	public $banmask  = '';
	
	public $channels = array();
	
	public function __construct($name, $user, $host, $banmask, $Server) {
		$this->id      = strtolower($name);
		$this->Server  = $Server;
		$this->name    = $name;
		$this->user    = $user;
		$this->host    = $host;
		$this->banmask = $banmask;
	}
	
	public function answerCTCP($message) {
		$this->notice("\x01".$message."\x01");
	}
	
	public function notice($message) {
		$this->Server->sendRaw('NOTICE '.$this->name.' :'.$message);
	}
	
}

?>
