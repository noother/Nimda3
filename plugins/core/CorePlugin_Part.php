<?php

class CorePlugin_Part extends Plugin {
	
	public $triggers = array('!part');
	
	function isTriggered() {
		if($this->User->nick != 'noother') return;
		
		if($this->data['isQuery'] && !isset($this->data['text'])) return;
		
		if(isset($this->data['text'])) {
			if(!isset($this->Server->channels[strtolower($this->data['text'])])) $this->reply('I\'m not in that channel');
			else $this->Server->channels[strtolower($this->data['text'])]->part();
		} else {
			$this->Channel->part();
		}
	}
	
}

?>
