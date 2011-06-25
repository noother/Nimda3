<?php

class CorePlugin_Botstats extends Plugin {
	
	public $triggers = array('!botstats');
	
	function isTriggered() {
		$servercount = sizeof($this->Bot->servers);
		$channelcount = 0;
		$usercount = 0;
		foreach($this->Bot->servers as $Server) {
			$channelcount+= sizeof($Server->channels);
			$usercount+= sizeof($Server->users);
		}
		
		$this->reply('Currently I\'m online on '.$servercount.' server(s) and '.$channelcount.' channel(s), seeing '.$usercount.' user(s).');
	}
	
}

?>
