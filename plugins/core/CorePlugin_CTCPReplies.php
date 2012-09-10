<?php

class CorePlugin_CTCPReplies extends Plugin {
	
	const REPLY_FINGER = 'Copyright (C) 2011-2012 "noother" - bugs to nimda@noother.net or https://github.com/noother/Nimda3/issues';
	
	function onCTCP() {
		switch($this->data['ctcp_command']) {
			
			case 'CLIENTINFO':
				$this->User->ctcpReply('CLIENTINFO', 'Nimda v'.$this->Bot->version.' - https://github.com/noother/Nimda3/ - Tags: CLIENTINFO,FINGER,PING,TIME,VERSION,ACTION');
			break;
			
			case 'FINGER':
				$this->User->ctcpReply('FINGER', self::REPLY_FINGER);
			break;
			
			case 'PING':
				$this->User->ctcpReply('PING', $this->data['text']);
			break;
			
			case 'TIME':
				$this->User->ctcpReply('TIME', date('r'));
			break;
			
			case 'VERSION':
				$text = 'Nimda v3.'.$this->Bot->version;
				$this->User->ctcpReply('VERSION', 'Nimda v'.$this->Bot->version);
			break;
			
		}
	}
	
}

?>
