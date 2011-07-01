<?php

require_once('IRC_Target.php');


final class IRC_User extends IRC_Target {
	
	public $nick     = '';
	public $user     = '';
	public $host     = '';
	public $realname = '';
	public $banmask  = '';
	public $modes    = array();
	public $mode     = '';
	
	public $channels = array();
	
	public function __construct($nick, $Server) {
		$this->id      = strtolower($nick);
		$this->Server  = $Server;
		$this->name    = $nick;
		$this->nick    = $nick;
	}
	
	public function answerCTCP($message) {
		$this->notice("\x01".$message."\x01");
	}
	
	public function notice($message) {
		$this->Server->sendRaw('NOTICE '.$this->name.' :'.$message);
	}
	
	public function changeNick($nick) {
		$new_id = strtolower($nick);
		
		foreach($this->channels as $Channel) {
			unset($Channel->users[$this->id]);
			$Channel->users[$new_id] = $this;
		}
		
		unset($this->Server->users[$this->id]);
		$this->Server->users[$new_id] = $this;
		
		$this->nick = $nick;
		$this->name = $nick;
		$this->id   = $new_id;
		$this->banmask = $this->nick.'!'.$this->user.'@'.$this->host;
	}
	
	public function remove() {
		foreach($this->channels as $Channel) {
			unset($Channel->users[$this->id]);
		}
		
		unset($this->Server->users[$this->id]);
	}
	
}

?>
