<?php

class CorePlugin_Servers extends Plugin {
	
	public $triggers = array('!servers');
	public $hideFromHelp = true;
	
	function isTriggered() {
		foreach($this->Bot->servers as $Server) {
			$output = $Server->host.': ';
			foreach($Server->channels as $Channel) {
				$output.= $Channel->name.' ';
			}
			$this->reply($output);
		}
	}
	
}

?>
