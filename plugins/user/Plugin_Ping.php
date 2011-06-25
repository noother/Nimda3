<?php

class Plugin_Ping extends Plugin {
	
	protected $triggers = array('!ping');
	
	function isTriggered() {
		$this->reply('Pong!');
	}
	
}

?>
