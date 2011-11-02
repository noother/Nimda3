<?php

class Plugin_Reverse extends Plugin {
	
	public $triggers = array('!reverse');
	
	public $helpCategory = 'Cryptography';
	public $usage = '<string>';
	public $helpText = 'Uses one of the most efficient encodings - It sends back your string reversed.';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}

		$this->reply(mb_strrev($this->data['text']));
	}
	
}

?>
