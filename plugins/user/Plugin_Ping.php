<?php

class Plugin_Ping extends Plugin {
	
	public $triggers = array('!ping', '!pong', '!pang', '!peng', '!pung');
	
	function isTriggered() {
		switch($this->data['trigger']) {
			case '!ping':
				$this->reply('Pong!');
			break;
			case '!pong':
				$this->reply('Ping?');
			break;
			case '!pang':
				$this->reply('Peng!');
			break;
			case '!peng':
				$this->reply('Pang!');
			break;
			case '!pung':
				$this->reply('Pyng?');
			break;
		}
	}
	
}

?>
