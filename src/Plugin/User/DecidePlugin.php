<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;

class DecidePlugin extends Plugin {
	
	public $triggers = array('!decide', '!choose');
	
	public $usage = '<option1> or <option2> or ...';
	public $helpTriggers = array('!decide');
	public $helpText = 'Helps you decide what to do.';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}

		$ors = array(" oder "," || ");
		
		$text = $this->data['text'];
		foreach($ors as $or) 
			$text = str_replace($or," or ",$text);
		
		$tmp = explode(" or ",$text);
		$rand = rand(0,sizeof($tmp)-1);
		
		$this->reply($this->User->nick.': '.$tmp[$rand]);
	}
	
}

?>
