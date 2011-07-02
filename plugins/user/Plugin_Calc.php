<?php

/**
 * caclulator using Google
 * original code from dr4k3
 * adapted for Nimda3 by livinskull
 */


class Plugin_Calc extends Plugin {
	
	public $triggers = array('!calc','!math');
    private $usage = 'Usage: %s <expression>';
	private $conenction_error = 'Error connection failed';
	private $parse_error = 'Error while parsing the result';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply(sprintf($this->usage, $this->data['trigger']));
			return;
		}

		$google = libHTTP::GET('www.google.com','/search?q='.urlencode($this->data['text']).'&hl=de&safe=off');
        
		if($google === false) {
			$this->reply($this->connection_error);
			return;
		}
		
		$result = preg_match_all("@<h2 class=r style=\"font-size:138%\"><b>(.*)</b></h2>@",$google['raw'],$matches);
		if($result == 0 || $result == false) {
			$this->reply($this->parse_error);
			return;
		}

		$result = preg_replace("/<sup>/",'^',$matches[1][0]);

		$this->reply(html_entity_decode(strip_tags($result)));
	}
	
}

?>
