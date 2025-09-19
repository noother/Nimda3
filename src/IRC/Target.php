<?php

namespace Nimda\IRC;

abstract class Target {
	protected $Server;
	public  $name = '';
	public  $id;
	
	private $_ctcpAllowed = false;
	
	abstract public function remove();
	
	public function privmsg($message, $bypass_queue=false) {
		if(strlen($message) == 0) return false;
		
		$message = strtr($message, array("\r" => ' ', "\n" => ' '));
		
		if($message[0] == "\x01" && !$this->_ctcpAllowed) $message = 'No.';
		
		
		/*
			irc max message length is 512 bytes including CRLF
			The irc message received by clients counts - NOT what we send
			Example: `:Nimda3!~Nimda3@unaffiliated/noother/bot/nimda PRIVMSG #nimda :Pong!`
			So, max privmsg-length (only text) =
				512
				- 1 (the starting :)
				- length(bots_banmask)
				- 9 (' PRIVMSG ')
				- length(target)
				- 2 (' :')
				- 2 (CRLF)
		*/
		
		$max_length = 498 - strlen($this->Server->Me->banmask) - strlen($this->name);
		
		$len = strlen($message);
		$message_length = ceil($len / ceil($len/$max_length));
		if($message_length <= $max_length - 10) {
			$message_length+= 10; // some margin for the wordwrap
		} else {
			$message_length = $max_length;
		}
		
		$messages = explode("\n", wordwrap($message, $message_length, "\n", true));
		
		foreach($messages as $message) {
			$message = trim($message);
			if(empty($message)) continue;
			$this->Server->sendRaw('PRIVMSG '.$this->name.' :'.$message, $bypass_queue);
		}
	}
	
	public function notice($message, $bypass_queue=false) {
		$this->Server->sendRaw('NOTICE '.$this->name.' :'.$message, $bypass_queue);
	}
	
	public final function action($message, $bypass_queue=false) {
		$this->ctcp('ACTION', $message, $bypass_queue);
	}
	
	public final function ctcp($command, $params="", $bypass_queue=false) {
		$send = $command;
		if(!empty($params)) $send.= ' '.$params;
		
		$this->_ctcpAllowed = true;
		$this->privmsg("\x01".$send."\x01", $bypass_queue);
		$this->_ctcpAllowed = false;
	}
	
	public final function ctcpReply($command, $message, $bypass_queue=false) {
		$this->notice("\x01".$command." ".$message."\x01", $bypass_queue);
	}
	
}

?>
