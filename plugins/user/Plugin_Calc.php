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
		
		$result = preg_match_all("@<h2 class=r style=\"font-size:138%\"><b>(.*)</b></h2>@", $google, $matches);
		if($result == 0 || $result == false) {
			$this->reply($this->parse_error);
			return;
		}

		$result = preg_replace("/<sup>/",'^',$matches[1][0]);

		$this->reply(utf8_encode(html_entity_decode(strip_tags($result))));
	}
	
}

?>
