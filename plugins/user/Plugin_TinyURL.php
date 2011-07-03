<?php

class Plugin_TinyURL extends Plugin {
	
	public $triggers = array('!tinyurl', '!tiny', '!tu');
	
	private $usage = 'Usage: %s <long_url>';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply(sprintf($this->usage, $this->data['trigger']));
			return;
		}
		
		$res = libHTTP::GET('tinyurl.com', '/api-create.php?url='.urlencode($this->data['text']));
		$this->reply($res['raw']);
	}
	
}

?>
