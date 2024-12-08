<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;

class ReversePlugin extends Plugin {
	
	public $triggers = array('!reverse');
	
	public $helpCategory = 'Cryptography';
	public $usage = '<string>';
	public $helpText = 'Uses one of the most efficient encodings - It sends back your string reversed.';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}

		$this->reply($this->User->nick.': '.mb_strrev($this->data['text']));
	}
	
}

?>
