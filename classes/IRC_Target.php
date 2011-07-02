<?php

require_once('IRC_Server.php');


abstract class IRC_Target {
	
	protected $Server;
	public  $name = '';
	public  $id;
	
	public $data = array(); // This unused property is meant for plugins to write to
	
	abstract public function remove();
	
	public final function privmsg($message, $bypass_queue=false) {
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
		
		while(!empty($message)) {
			$to_send = substr($message, 0, $max_length);
			$this->Server->sendRaw('PRIVMSG '.$this->name.' :'.$to_send, $bypass_queue);
			
			$message = substr($message, $max_length);
		}
		
	}
	
	public final function sendCTCP($message, $bypass_queue=false) {
		$this->privmsg("\x01".$message."\x01", $bypass_queue);
	}
	
	public final function action($message, $bypass_queue=false) {
		$this->sendCTCP('ACTION '.$message, $bypass_queue);
	}
	
}

?>
