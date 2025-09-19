<?php

namespace Nimda\Plugin\User;

use Nimda\Common;
use Nimda\Plugin\Plugin;
use noother\Library\Time;

class UptimePlugin extends Plugin {
	
	public $triggers = array('!uptime');
	
	public $helpText = 'Sends back the current and total uptime of the bot.';
	
	private $started = 0;
	
	function onLoad() {
		$this->started = time();
	}
	
	function isTriggered() {
		$this->reply(sprintf("Uptime: %s - Total Uptime: %s",
			Time::secondsToString(Common::getTime() - $this->started),
			Time::secondsToString($this->getVar('total_uptime', 0) + Common::getTime() - $this->started)
		));
	}
	
	function onUnload() {
		$this->saveVar('total_uptime', $this->getVar('total_uptime', 0) + time() - $this->started);
	}
	
}

?>
