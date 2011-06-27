<?php

class Plugin_Hash extends Plugin {
	
	public $triggers = array('!hash');
	
	function onLoad() {
		$algos = hash_algos();
		foreach($algos as $algo) {
			if(strstr($algo, ',') === false) {
				$this->triggers[] = '!'.$algo;
			}
		}
	}
	
	function isTriggered() {
		if($this->data['trigger'] == '!hash' && !isset($this->data['text'])) {
			$this->reply('Usage: !hash <algo> <text> or !hash algos for a list');
			return;
		}
		
		if($this->data['trigger'] == '!hash') {
			$tmp = explode(' ', $this->data['text'], 2);
			$algo = $tmp[0];
			if(isset($tmp[1])) $text = $tmp[1];
		} else {
			$algo = substr($this->data['trigger'], 1);
			if(isset($this->data['text'])) $text = $this->data['text'];
		}
		
		if ($algo == 'algos') {
			/* output list of available algorithms */
			$this->reply('Algos: '.implode('; ', hash_algos()));
		} elseif(!isset($text)) {
			/* show info about algorithm */
			$x = hash($algo, 'abc', false);
	        $len = strlen($x)/2;
	        $this->reply(sprintf('Length: %d bit (%d bytes)', $len*8, $len));
		} else {
			/* hash input with algorithm */
			$this->reply('Result: '.hash($algo, $text, false));
		}

	}
	
}

?>
