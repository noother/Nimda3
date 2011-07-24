<?php

class Plugin_TinyURL extends Plugin {
	
	public $triggers = array('!tinyurl', '!tiny', '!tu');
	
	private $usage = 'Usage: %s <long_url>';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply(sprintf($this->usage, $this->data['trigger']));
			return;
		}
		
		$tinyurl = libInternet::tinyURL($this->data['text']);
		
		if(strlen($tinyurl) <= strlen($this->data['text'])) {
			$this->reply($tinyurl);
		} else {
			$this->reply($tinyurl.' - Now your URL is even longer than before - Good job!');
		}
		
	}
	
}

?>
