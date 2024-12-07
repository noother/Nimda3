<?php

namespace Nimda\Plugin\User\Omegle;

class Chatter {
	
	public $User;
	public $Channel;
	public $Server;
	public $Omegle;
	
	public $connected = false;
	
	function __construct($User, $Channel, $Server) {
		$this->User    = $User;
		$this->Channel = $Channel;
		$this->Server  = $Server;
		$this->Omegle  = new Omegle();
		
		$this->connected = $this->Omegle->start();
	}
	
	function sendMessage($message) {
		$message = $this->User->nick.': '.$message;
		if($this->Channel) $this->Channel->privmsg($message);
		else $this->User->privmsg($message);
	}
	
	function sendAction($message) {
		if($this->Channel) $this->Channel->action($message);
		else $this->User->action($message);
	}
	
}

?>
