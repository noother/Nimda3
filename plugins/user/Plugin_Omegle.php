<?php

require_once('Omegle/Chatter.php');
require_once('Omegle/Omegle.php');

class Plugin_Omegle extends Plugin {
	
	public $triggers = array('!chat', '!omegle', '!stopchat', '!stopomegle');
	
	public $helpTriggers = array('!chat');
	public $helpText = 'Chat with one of the many different personalities of Nimda!';
	public $helpCategory = 'Internet';
	
	private $chatters = array();
	
	function isTriggered() {
		switch($this->data['trigger']) {
			case '!chat': case '!omegle':
				$this->startChat();
			break;
			case '!stopchat': case '!stopomegle':
				$this->stopChat();
			break;
			default:
				$chatterId = $this->getChatterId();
				if(isset($this->chatters[$chatterId]) && $this->data['trigger'] == $this->chatters[$chatterId]->Server->Me->nick.':') {
					$this->chatters[$chatterId]->Omegle->send($this->data['text']);
				}
			break;
		}
	}
	
	private function startChat() {
		if(isset($this->chatters[$this->getChatterId()])) {
			$this->reply('You already have a chat session running.');
			return;
		}
		
		$this->reply('Ok, starting chat session.');
		
		$this->chatters[$this->getChatterId()] = $Chatter = new Chatter(
			$this->User,
			$this->Channel ? $this->Channel : false,
			$this->Server
		);
		
		$this->interval = 1;
		if(array_search($Chatter->Server->Me->nick, $this->triggers) === false) {
			$this->triggers[] = $Chatter->Server->Me->nick.':';
		}
	}
	
	private function stopChat() {
		$chatterId = $this->getChatterId();
		if(isset($this->chatters[$chatterId])) {
			$this->removeSession($chatterId);
			$this->reply('You have disconnected.');
		} else {
			$this->reply('You do not have a chat session running.');
		}
	}
	
	private function removeSession($key) {
		unset($this->chatters[$key]);
		if(empty($this->chatters)) $this->interval = 0;
	}
	
	function onInterval() {
		foreach($this->chatters as $key => $Chatter) {
			while(false !== $msg = fgets($Chatter->Omegle->listener)) {
				$msg = trim($msg);
				echo "Omegle: ".$msg."\n";
				switch($msg) {
					case 'connected':
						$Chatter->sendMessage('You may now chat with me using '.$Chatter->Server->Me->nick.': <your_message>. If you want to quit, type !stopchat.');
					break;
					case 'disconnected':
						$Chatter->sendAction('disconnects '.$Chatter->User->nick.'\'s chat');
						$this->removeSession($key);
					break;
					case 'message':
						$message = trim(fgets($Chatter->Omegle->listener));
						$Chatter->sendMessage($message);
					break;
					case 'error':
						$message = trim(fgets($Chatter->Omegle->listener));
						$Chatter->sendMessage("An error occurred: ".$message);
						$this->removeSession($key);
					break;
				}
			}
		}
	}
	
	private function getChatterId() {
		$id = $this->User->nick;
		if($this->Channel) $id.= ':'.$this->Channel->name;
		
	return $id;
	}
	
}

?>
