<?php

require_once('IRC_Server.php');


abstract class IRC_Target {
	
	protected $Server;
	public  $name = '';
	public  $id;
	
	public $data = array(); // This unused property is meant for plugins to write to
	
	public function privmsg($message) {
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
		
		$max_length = 498 - strlen($this->Server->myBanmask) - strlen($this->name); 
		
		while(!empty($message)) {
			$to_send = substr($message, 0, $max_length);
			$this->Server->sendRaw('PRIVMSG '.$this->name.' :'.$to_send);
			
			$message = substr($message, $max_length);
		}
		
	}
	
	public function sendCTCP($message) {
		$this->privmsg("\x01".$message."\x01");
	}
	
	public function action($message) {
		$this->sendCTCP('ACTION '.$message);
	}
	
}

?>
