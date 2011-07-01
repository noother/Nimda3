<?php

class CorePlugin_Join extends Plugin {
	
	public $triggers = array('!join');
	
	function isTriggered() {
		if($this->User->nick != 'noother') return;
		if(!isset($this->data['text'])) return;
		
		if(isset($this->Server->channels[strtolower($this->data['text'])])) {
			$this->reply('I\'m already online in '.$this->data['text']);
			return;
		}
		
		$this->Server->joinChannel($this->data['text']);
		$this->reply('Joined channel '.$this->data['text']);
	}
	
}

?>
