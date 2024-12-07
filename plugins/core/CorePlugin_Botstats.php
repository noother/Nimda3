<?php

use noother\Library\Time;

class CorePlugin_Botstats extends Plugin {
	
	public $triggers = array('!botstats', '!perf');
	
	public $helpText = 'Displays some profiling information';
	
	private $started;
	
	function onLoad() {
		$this->started = time();
	}
	
	function isTriggered() {
		$uptime = Time::secondsToString($this->Bot->time - $this->started);
		
		$servercount = sizeof($this->Bot->servers);
		$channelcount = 0;
		$usercount = 0;
		foreach($this->Bot->servers as $Server) {
			$channelcount+= sizeof($Server->channels);
			$usercount+= sizeof($Server->users);
		}
		
		$mem_current = number_format(memory_get_usage()/1000000,2);
		$mem_peak    = number_format(memory_get_peak_usage()/1000000,2);
		
		$this->reply(sprintf(
			"\x02Uptime:\x02 %s. \x02Memory:\x02 %.2f MB (%.2f MB max). \x02SQL:\x02 %d queries total (%.2f q/s). \x02Jobs:\x02 %d (%d max). \x02Timers:\x02 %d. \x02Servers:\x02 %d, \x02Channels:\x02 %d, \x02Users:\x02 %d. ",
				$uptime,
				$mem_current,
				$mem_peak,
				$this->MySQL->queryCount,
				$this->MySQL->queryCount / ($this->Bot->time - $this->started),
				$this->Bot->jobCount,
				$this->Bot->jobCountMax,
				$this->Bot->timerCount,
				$servercount,
				$channelcount,
				$usercount
		));
	}
	
}

?>

