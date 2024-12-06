<?php

use noother\Network\SimpleHTTP;

class Plugin_WeChall extends Plugin {
	
	public $triggers = array('!wechall', '!wc');
	public $usage = '[<wechall_command>]';
	
	public $helpCategory = 'Challenges';
	public $helpTriggers = array('!wechall');
	public $helpText = "Uses the wechall API to print challenge players/sites statistics";


	function isTriggered() {
		if(isset($this->data['text'])) $target = $this->data['text'];
		else $target = $this->User->name;
		
		if($target[0] == '!' && strstr($target, ' ') === false) $target.= ' '.$this->User->name;
		$html = SimpleHTTP::GET('http://www.wechall.net/wechall.php?username='.urlencode($target));
		
		$this->reply($html);
	}
	
}

?>
