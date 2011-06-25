<?php

require_once('IRC_Server.php');


abstract class IRC_Target {
	
	protected $Server;
	public  $name = '';
	public  $id;
	
	public $data = array(); // This unused property is meant for plugins to write to
	
	public function privmsg($message) {
		$this->Server->sendRaw('PRIVMSG '.$this->name.' :'.$message);
	}
	
	public function sendCTCP($message) {
		$this->privmsg("\x01".$message."\x01");
	}
	
	public function action($message) {
		$this->sendCTCP('ACTION '.$message);
	}
	
}

?>
