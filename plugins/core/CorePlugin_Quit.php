<?php

class CorePlugin_Quit extends Plugin {
	
	public $triggers = array('!quit');
	
	function isTriggered() {
		if($this->User->name != 'noother') return;
		
		if(isset($this->data['text'])) $this->Server->quit($this->data['text']);
		else $this->Server->quit();
	}
	
	function onMeQuit() {
		unset($this->Bot->servers[$this->Server->id]);
	}
	
}

?>
