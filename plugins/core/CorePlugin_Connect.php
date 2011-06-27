<?php

class CorePlugin_Connect extends Plugin {

	public $triggers = array('!connect');
	
	function isTriggered() {
		if($this->User->name != 'noother') return;
		if(!isset($this->data['text'])) return;
		
		$tmp = explode(' ',$this->data['text']);
		if(sizeof($tmp) != 3) return;
		
		$data = array(
			'host' => $tmp[0],
			'port' => $tmp[1],
			'ssl'  => $tmp[2],
			'my_username' => 'Nimda3',
			'my_hostname' => 'Nimda3',
			'my_servername' => 'Nimda3',
			'my_realname' => 'noother\'s new bot',
			'id' => strtolower($tmp[0])
		);
		
		$this->Bot->connectServer($data);
	}
	
}

?>
