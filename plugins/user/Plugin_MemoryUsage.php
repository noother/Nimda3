<?php

class Plugin_MemoryUsage extends Plugin {
	
	protected $triggers = array('!mem');
	
	function isTriggered() {
		$current = number_format(memory_get_usage()/1000000,2);
		$peak    = number_format(memory_get_peak_usage()/1000000,2);
		
		$this->reply('Currently there are '.$current.'MB in use. Max memory peak was '.$peak.'MB.');
	}
	
}

?>
