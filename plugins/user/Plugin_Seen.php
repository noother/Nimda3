<?php

class Plugin_Seen extends Plugin {
	
	public $triggers = array('!seen');
	
	public $helpText = 'Sends back information about when the user was last seen.';
	public $usage = '<nick>';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}
		
		$data = $this->getVar($this->data['text']);
		if($data === false) {
			$this->reply('I don\'t know '.$this->data['text'].'.');
			return;
		}
		
		$this->reply($this->assembleText($data));
	}
	
	private function assembleText($data) {
		$text = 'I\'ve last seen '.$data['nick'].' '.libTime::secondsToString(time()-$data['time']).' ago ';
		if($data['server'] != $this->Server->host) $text.= 'on '.$data['server'].' ';
		
		switch($data['action']) {
			case 'ACTION':
				$text.= 'in '.$data['channel'].' stating that he '.$data['text'].'.';
			break;
			case 'PRIVMSG':
				$text.= 'in '.$data['channel'].' saying "'.$data['text'].'".';
			break;
			case 'JOIN':
				$text.= 'joining '.$data['channel'].'.';
			break;
			case 'PART':
				$text.= 'parting '.$data['channel'];
				if($data['message'] !== false) {
					$text.= ' with message '.$data['message'].'.';
				} else {
					$text.= '.';
				}
			break;
			/*
			case 'QUIT':
				$text.= 'quitting the server with message "'.$data['message'].'".';
			break;
			*/
		}
		
	return $text;
	}
	
	function onAction() {
		if($this->data['isQuery']) return;
		
		$this->saveVar($this->User->nick, array(
			'action'  => 'ACTION',
			'server'  => $this->Server->host,
			'channel' => $this->Channel->name,
			'nick'    => $this->User->nick,
			'text'    => $this->data['text'],
			'time'    => $this->Bot->time
		));
	}
	
	
	function onChannelMessage() {
		$this->saveVar($this->User->nick, array(
			'action'  => 'PRIVMSG',
			'server'  => $this->Server->host,
			'channel' => $this->Channel->name,
			'nick'    => $this->User->nick,
			'text'    => $this->data['text'],
			'time'    => $this->Bot->time
		));
	}
	
	function onJoin() {
		$this->saveVar($this->User->nick, array(
			'action' => 'JOIN',
			'server' => $this->Server->host,
			'channel'=> $this->Channel->name,
			'nick'   => $this->User->nick,
			'time'   => $this->Bot->time
		));
	}
	
	function onPart() {
		$this->saveVar($this->User->nick, array(
			'action'  => 'PART',
			'server'  => $this->Server->host,
			'channel' => $this->Channel->name,
			'nick'    => $this->User->nick,
			'message' => isset($this->data['part_message']) ? $this->data['part_message'] : false,
			'time'    => $this->Bot->time
		));
	}
	
	/*
	function onQuit() {
		$this->saveVar($this->User->nick, array(
			'action'  => 'QUIT',
			'server'  => $this->Server->host,
			'nick'    => $this->User->nick,
			'message' => $this->data['quit_message'],
			'time'    => $this->Bot->time
		));
	}
	*/
	
}

?>
