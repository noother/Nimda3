<?php

class Plugin_Hash extends Plugin {
	
	protected $triggers = array('!hash');
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply('Usage: !hash <algo> <text> or !hash algos for a list');
			return;
		}

		$algo = strtok($this->data['text'], ' ');
		$text = $this->substrFrom($this->data['text'], ' ');

		if ($algo == 'algos') {
			/* output list of available algorithms */
			$this->reply('Algos: '.implode('; ', hash_algos()));
		} else if ($text == '') {
			/* show info about algorithm */
			$x = hash($algo, 'abc', false);
	        $len = strlen($x)/2;
	        $this->reply(sprintf('Length: %d bit (%d bytes)', $len*8, $len));
		} else {
			/* hash input with algorithm */
			$this->reply('Result: '.hash($algo, $text, false));
		}

	}
	
	/* borrowed from gizmore's util/Common.php :) */
	function substrFrom($string, $from, $default='') {
		$pos = strpos($string, $from);
		
		if ($pos === false) 
			return $default;
		
		$len = strlen($from);
		return substr($string, $pos+$len);
	}
	
}

?>
