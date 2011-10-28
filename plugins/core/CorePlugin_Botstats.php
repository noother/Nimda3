<?php

class CorePlugin_Botstats extends Plugin {
	
	public $triggers = array('!botstats');
	
	public $helpText = 'Displays on how many servers/channel the bot currently is and how many users he sees';
	
	function isTriggered() {
		$servercount = sizeof($this->Bot->servers);
		$channelcount = 0;
		$usercount = 0;
		foreach($this->Bot->servers as $Server) {
			$channelcount+= sizeof($Server->channels);
			$usercount+= sizeof($Server->users);
		}
		
		$this->reply(sprintf(
			"Currently I'm online on %s and %s, seeing %s.",
				libString::plural('server', $servercount),
				libString::plural('channel', $channelcount),
				libString::plural('user', $usercount)
		));
	}
	
}

?>
