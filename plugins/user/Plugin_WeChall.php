<?php

class Plugin_WeChall extends Plugin {
	
	protected $triggers = array('!wechall', '!wc', '!wcc');
	private $usage = 'Usage: %s <nick>';


	function isTriggered() {
		if(isset($this->data['text'])) $target = $this->data['text'];
		else $target = $this->User->name;
		
		
		if($this->data['trigger'] == '!wcc') {
			$result = libHTTP::GET('www.wechall.net', '/wechallchalls.php?username='.urlencode($target));
			$tmp = $result['content'][0];
			
			$this->reply($tmp);
			return;
		}
		
		
		
		$result = libHTTP::GET('www.wechall.net','/wechall.php?username='.urlencode($target));
		$tmp = $result['content'][0];

		$this->reply($tmp);
		
	}
	
}

?>
