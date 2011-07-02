<?php

class Plugin_Versus extends Plugin {
	
	public $triggers = array('!vs');
	private $usage = 'Usage: %s <word1>,<word2>';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply(sprintf($this->usage,$this->data['trigger']));
			return;
		}

		$input = explode(',',$this->data['text']);
		
		if(empty($input[1])){
			$this->reply(sprintf($this->usage,$this->data['trigger']));
			return;
		}
		
		$word1Hits = libInternet::googleResults($input[0]);
		$word2Hits = libInternet::googleResults($input[1]);
		
		if ($word1Hits + $word2Hits == 0){
			$zero = array('zero', 'oh', 'null', 'nil', 'nought');
			$this->reply('I can\'t compare '.$zero[rand(0,4)].' with '.$zero[rand(0,4)].'.');
			return;
		}
		
		$this->reply('('.number_format($word1Hits,0,',','.').") \x02".$input[0]."\x02 ".$this->getBar($word1Hits,$word2Hits)."\x02 ".$input[1]."\x02 (".number_format($word2Hits,0,',','.').")");
	}
	
	private function getBar($number1, $number2){
		$divider = ($number1 + $number2) / 20;
		$output = '['.str_repeat('=',round($number1/$divider));
		$output .= '|';
		$output .= str_repeat('=',round($number2/$divider)).']';
		return $output;
	}
}

?>
