<?php

class Plugin_TinyURL extends Plugin {
	
	public $triggers = array('!tinyurl', '!tiny', '!tu');
	public $usage = '<long_url>';
	
	public $helpCategory = 'Internet';
	public $helpTriggers = array('!tinyurl');
	public $helpText = "Gives back a shortenend url from tinyurl.com";
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
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
