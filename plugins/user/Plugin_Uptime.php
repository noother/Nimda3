<?php

class Plugin_Uptime extends Plugin {
	
	public $triggers = array('!uptime');
	
	public $helpText = 'Sends back the current and total uptime of the bot.';
	
	private $started = 0;
	
	function onLoad() {
		$this->started = time();
		if($this->getVar('total_uptime') === false) $this->saveVar('total_uptime', 0);
	}
	
	function isTriggered() {
		$this->reply(sprintf("Uptime: %s - Total Uptime: %s",
			libTime::secondsToString($this->Bot->time - $this->started),
			libTime::secondsToString($this->getVar('total_uptime') + $this->Bot->time - $this->started)
		));
	}
	
	function onUnload() {
		$this->saveVar('total_uptime', $this->getVar('total_uptime') + time() - $this->started);
	}
	
}

?>
