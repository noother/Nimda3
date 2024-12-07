<?php

use noother\Library\Time;

class Plugin_Uptime extends Plugin {
	
	public $triggers = array('!uptime');
	
	public $helpText = 'Sends back the current and total uptime of the bot.';
	
	private $started = 0;
	
	function onLoad() {
		$this->started = time();
	}
	
	function isTriggered() {
		$this->reply(sprintf("Uptime: %s - Total Uptime: %s",
			Time::secondsToString($this->Bot->time - $this->started),
			Time::secondsToString($this->getVar('total_uptime', 0) + $this->Bot->time - $this->started)
		));
	}
	
	function onUnload() {
		$this->saveVar('total_uptime', $this->getVar('total_uptime', 0) + time() - $this->started);
	}
	
}

?>
