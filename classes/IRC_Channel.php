<?php

require_once('IRC_Target.php');


final class IRC_Channel extends IRC_Target {
	
	public $topic = '';
	public $modes = array();
	public $users = array();
	
	private $Bot;
	
	public function __construct($name, $Server) {
		$this->id     = strtolower($name);
		$this->Server = $Server;
		$this->name   = $name;
		$this->Bot    = $Server->Bot;
		
		$this->sendMode();
		$this->sendWho();
	}
	
	private function sendMode() {
		$this->Server->sendRaw('MODE '.$this->name, true);
	}
	
	private function sendWho() {
		$this->Server->sendRaw('WHO '.$this->name, true);
	}
	
	public function privmsg($message, $bypass_queue=false) {
		if(isset($this->modes['c'])) {
			$message = libIRC::stripControlChars($message);
		}
		parent::privmsg($message, $bypass_queue);
	}
	
	public function part($message=false) {
		if($message) $this->Server->sendRaw('PART '.$this->name.' :'.$message);
		else         $this->Server->sendRaw('PART '.$this->name);
	}
	
	public function kick($User, $message=false) {
		if($message !== false) $this->Server->sendRaw('KICK '.$this->name.' '.$User->nick.' :'.$message);
		else                   $this->Server->sendRaw('KICK '.$this->name.' '.$User->nick);
	}
	
	public function invite($User) {
		$this->Server->sendRaw('INVITE '.$User.' '.$this->name);
	}
	
	public function sendNames() {
		$this->Server->sendRaw('NAMES '.$this->name);
	}
	
	public function setTopic($topic) {
		$this->Server->sendRaw('TOPIC '.$this->name.' :'.$topic);
	}
	
	public function setMode($mode) {
		$this->Server->sendRaw('MODE '.$this->name.' '.$mode);
	}
	
	public function setModes($modes) {
		// TODO: set all modes with a single write (or as least as possible)
		foreach($modes as $mode) {
			$this->setMode($mode);
		}
	}
	
	public function setUserMode($mode, $user) {
		$this->Server->sendRaw('MODE '.$this->name.' '.$mode.' '.$user);
	}
	
	public function setUserModes($usermodes) {
		foreach($usermodes as $data) {
			$this->setUserMode($data['mode'], $data['user']);
		}
	}
	
	/*
	public function setUserModes($usermodes) {
		// broken, servers only accept N modes at a time - need to implement that before activating this again
		$modes_give = "";
		$modes_take = "";
		$users_give = "";
		$users_take = "";
		
		foreach($usermodes as $usermode) {
			switch($usermode['mode']{0}) {
				case '+':
					$modes_give.= $usermode['mode']{1};
					if(!empty($users_give)) $users_give.= ' ';
					$users_give.= $usermode['user'];
				break;
				case '-':
					$modes_take.= $usermode['mode']{1};
					if(!empty($users_take)) $users_take.= ' ';
					$users_take.= $usermode['user'];
				break;
			}
		}
		
		$modes = '';
		$users = '';
		if(!empty($modes_take)) {
			$modes.= '-'.$modes_take;
			$users.= $users_take;
		}
		if(!empty($modes_give)) {
			$modes.= '+'.$modes_give;
			if(!empty($modes_take)) $users.= ' ';
			$users.= $users_give;
		}
		
		$this->setUserMode($modes, $users);
	}
	*/
	
	public function remove() {
		foreach($this->users as $User) {
			$this->removeUser($User);
		}
		unset($this->Server->channels[$this->id]);
	}
	
	public function addUser($User) {
		$User->modes[$this->id]    = '';
		$this->users[$User->id]    = $User;
		$User->channels[$this->id] = $this;
	}
	
	public function removeUser($User) {
		unset($this->users[$User->id]);
		
		if(sizeof($User->channels) == 1) {
			$User->remove();
		} else {
			unset($this->users[$User->id]);
			unset($User->channels[$this->id]);
		}
	}
	
	public function saveVar($name, $value) {
		$this->Bot->savePermanent($name, $value, 'channel', $this->Server->id.':'.$this->id);
	}
	
	public function getVar($name, $default=false) {
		$value = $this->Bot->getPermanent($name, 'channel', $this->Server->id.':'.$this->id);
		if($value === false) return $default;
		
	return $value;
	}
	
	public function removeVar($name) {
		$this->Bot->removePermanent($name, 'channel', $this->Server->id.':'.$this->id);
	}
	
}

?>
