<?php

class Plugin_WeChall extends Plugin {
	
	public $triggers = array('!wechall', '!wc');
	private $usage = 'Usage: %s <nick>';


	function isTriggered() {
		if(isset($this->data['text'])) $target = $this->data['text'];
		else $target = $this->User->name;
		
		if($target{0} == '!' && strstr($target, ' ') === false) $target.= ' '.$this->User->name;
		$html = libHTTP::GET('http://www.wechall.net/wechall.php?username='.urlencode($target));
		
		$this->reply($html);
	}
	
}

?>
