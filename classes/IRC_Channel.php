<?php

require_once('IRC_Target.php');


final class IRC_Channel extends IRC_Target {
	
	public $topic = '';
	public $users = array();
	
	public function __construct($name, $Server) {
		$this->id     = strtolower($name);
		$this->Server = $Server;
		$this->name   = $name;
		
		$this->sendWho();
	}
	
	private function sendWho() {
		$this->Server->sendRaw('WHO '.$this->name);
	}
	
	public function part($message=false) {
		if($message) $this->Server->sendRaw('PART '.$this->name.' :'.$message);
		else         $this->Server->sendRaw('PART '.$this->name);
	}
	
	public function kick($User, $message=false) {		
		if($message !== false) $this->Server->sendRaw('KICK '.$this->name.' '.$User->Name.' :'.$message);
		else                   $this->Server->sendRaw('KICK '.$this->name.' '.$User->Name);
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
	
}

?>
