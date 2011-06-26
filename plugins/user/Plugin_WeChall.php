<?php

class Plugin_WeChall extends Plugin {
	
	protected $triggers = array('!wechall', '!wc', '!wcc');
	private $usage = 'Usage: %s <nick>';


	function isTriggered() {
		if(isset($this->data['text'])) $target = $this->data['text'];
		else $target = $this->User->name;
		
		switch($this->data['trigger']) {
			case '!wcc':
				$result = libHTTP::GET('www.wechall.net', '/wechallchalls.php?username='.urlencode($target));
				$tmp = $result['content'][0];
			
				$this->reply($tmp);
			break;
			default:
				if($target{0} == '!' && strstr($target, ' ') === false) $target.= ' '.$this->User->name;
				$result = libHTTP::GET('www.wechall.net','/wechall.php?username='.urlencode($target));
				$tmp = $result['content'][0];

				$this->reply($tmp);
			break;
		}
		
	}
	
}

?>
