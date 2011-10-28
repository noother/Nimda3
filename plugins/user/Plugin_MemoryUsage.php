<?php

class Plugin_MemoryUsage extends Plugin {
	
	public $triggers = array('!mem');
	
	public $helpText = 'Displays how much ram the bot is currently using and what the usage peak was.';
	
	function isTriggered() {
		$current = number_format(memory_get_usage()/1000000,2);
		$peak    = number_format(memory_get_peak_usage()/1000000,2);
		
		$this->reply('Currently there are '.$current.'MB in use. Max memory peak was '.$peak.'MB.');
	}
	
}

?>
