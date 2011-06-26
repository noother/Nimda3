<?php

class Plugin_Decide extends Plugin {
	
	protected $triggers = array('!decide', '!choose');
	private $usage = 'Usage: !decide <option1> or <option2> or ...';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply($this->usage);
			return;
		}

		$ors = array(" oder "," || ");
		
		$text = $this->data['text'];
		foreach($ors as $or) 
			$text = str_replace($or," or ",$text);
		
		$tmp = explode(" or ",$text);
		$rand = rand(0,sizeof($tmp)-1);
		
		$this->reply($tmp[$rand]);
	}
	
}

?>
