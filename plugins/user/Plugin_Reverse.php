<?php

class Plugin_Reverse extends Plugin {
	
	protected $triggers = array('!reverse');
	
	function isTriggered() {
		if (!isset($this->data['text'])) {
			$this->reply('Usage: !reverse <text>');
			return;
		}

		$this->reply($this->mb_strrev($this->data['text']));
	}
	

	private function mb_strrev($text) {
		$length = mb_strlen($text);
		$output = '';

		while ($length >= 0)
			$output .= mb_substr($text, $length--, 1);

		
		return $output;
	}


}

?>
