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
	public $nickservStatus = 0;
	
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
	
	public function isIdentified() {
		if($this->nickservStatus == 3) return true;
		if(!$this->Server->nickservIdentifyCommand) return false;
			
		$this->Server->NickServ->privmsg($this->Server->nickservIdentifyCommand.' '.$this->nick);
		$this->Server->flushSendQueue();
		
		$c = 100;
		while(true) {
			$data = $this->Server->getData();
			if(!$data) {
				usleep(20000);
				if(--$c <= 0) return false;
				continue;
			}
			
			if($data['command'] == 'NOTICE' && isset($data['User']) && $data['User']->id == 'nickserv') {
				$tmp = explode(' ', $data['text']);
				$status = $tmp[2];
				
				switch($this->Server->nickservIdentifyCommand) {
					case 'ACC':
						$nick = $tmp[0];
					break;
					case 'STATUS':
						$nick = $tmp[1];
					break;
				}
				
				if(strtolower($nick) == $this->id) {
					$this->nickservStatus = $status;
					break;
				}
			} else {
				$this->Server->enqueueRead($data);
			}
		}
		
		
		return ($this->nickservStatus == 3);
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
		$this->nickservStatus = 0;
	}
	
	public function remove() {
		foreach($this->channels as $Channel) {
			unset($Channel->users[$this->id]);
		}
		
		unset($this->Server->users[$this->id]);
	}
	
}

?>
