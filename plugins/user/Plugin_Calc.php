<?php

/**
 * caclulator using Google
 * original code from dr4k3
 * adapted for Nimda3 by livinskull
 */


class Plugin_Calc extends Plugin {
	
	public $triggers = array('!calc','!math');
	
	public $helpTriggers = array('!calc');
	public $helpText = 'Evaluates <expression> and prints the output.';
	public $usage = '<expression>';
	
	private $connection_error = 'Error connection failed';
	private $parse_error = 'Error while parsing the result';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}

		$google = libHTTP::GET('http://www.google.com/search?q='.urlencode($this->data['text']).'&hl=de&safe=off');
		if($google === false) {
			$this->reply($this->connection_error);
			return;
		}
		
		if(!preg_match('#<h2 class="r" dir="ltr" style="font-size:138%;display:inline">(.*?)</h2>#s', $google, $arr)) {
			$this->reply($this->parse_error);
			return;
		}

		$result = preg_replace("/<sup>/",'^',$arr[1]);
		
		while(false !== strstr($result, '  ')) $result = str_replace('  ', ' ', $result);

		$this->reply(utf8_encode(html_entity_decode(strip_tags($result))));
	}
	
}

?>
