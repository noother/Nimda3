<?php

class Plugin_Versus extends Plugin {
	
	public $triggers = array('!vs');
	public $usage = '<word1>,<word2>';
	
	public $helpCategory = 'Internet';
	public $helpText = "Compares the number of google results for 2 terms";
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}

		$input = explode(',',$this->data['text']);
		
		if(empty($input[1])){
			$this->reply(sprintf($this->usage,$this->data['trigger']));
			return;
		}
		
		$terms = array();
		for($i=0;$i<2;$i++) {
			$terms[$i] = trim($input[$i]);
			if(str_word_count($terms[$i]) == 1) $terms[$i] = '"'.$terms[$i].'"';
		}
		
		$word1Hits = libInternet::googleResults($terms[0]);
		$word2Hits = libInternet::googleResults($terms[1]);
		
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
