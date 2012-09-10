<?php

require_once('Omegle/Omegle.php');

class Plugin_Omeglespy extends Plugin {
	
	public $triggers = array('!omeglespy', '!chatspy', '!eavesdrop', '!stopspy', '!alice', '!bob');
	
	public $helpTriggers = array('!omeglespy');
	public $helpText = 'Lets you eavesdrop a conversation between two random users on omegle.com. You may inject messages with !alice <text> and !bob <text>';
	public $helpCategory = 'Internet';
	
	private $spies = array();
	
	function isTriggered() {
		
		if($this->Channel) {
			$Target = $this->Channel;
		} else {
			$Target = $this->User;
		}
		
		switch($this->data['trigger']) {
			case '!omeglespy': case '!chatspy': case '!eavesdrop':
				$this->addSpy($Target);
			break;
			
			case '!stopspy':
				$this->removeSpy($Target);
			break;
			
			case '!alice': case '!bob':
				$this->injectMessage($Target);
			break;
		}
		
	}
	
	function onInterval() {
		foreach($this->spies as $key => $spy) {
			$victims = array('Alice' => $spy['Alice'], 'Bob' => $spy['Bob']);
			foreach($victims as $name => $Victim) {
				
				if($name == 'Alice') $OtherGuy = $spy['Bob'];
				else $OtherGuy = $spy['Alice'];
				
				while(false !== $msg = fgets($Victim->listener)) {
					$msg = trim($msg);
					echo "OmegleSpy: ".$msg."\n";
					switch($msg) {
						case 'connected':
							$spy['Target']->privmsg($name.' connected');
						break;
						case 'disconnected':
							$spy['Target']->privmsg($name.' disconnected, shutting down.');
							$this->removeSpy($spy['Target']);
						break;
						case 'typing':
							$OtherGuy->typing();
						break;
						case 'stoppedTyping':
							$OtherGuy->stoppedTyping();
						break;
						case 'message':
							$message = trim(fgets($Victim->listener));
							$spy['Target']->privmsg("\x02[OmegleSpy]\x02 <".$name."> ".$message);
							$check = $OtherGuy->send($message);
							if($check === 'spam') {
								$spy['Target']->privmsg('The previous message was not sent because it would get us banned.');
							}
						break;
						case 'error':
							$message = trim(fgets($Victim->listener));
							$spy['Target']->privmsg("An error occurred: ".$message);
							$this->removeSpy($spy['Target']);
						break;
					}
				}
			}
		}
	}
	
	private function injectMessage($Target) {
		if(!isset($this->data['text'])) {
			$this->reply('No message to inject');
			return;
		}
		
		if(!isset($this->spies[$Target->id])) {
			$this->reply('There is no spy running here.');
			return;
		}
		
		switch($this->data['trigger']) {
			case '!alice':
				$name = 'Alice';
				$send_to = 'Bob';
			break;
			case '!bob':
				$name = 'Bob';
				$send_to = 'Alice';
			break;
		}
		
		$check = $this->spies[$Target->id][$send_to]->send($this->data['text']);
		if($check === 'spam') {
			$this->reply('The message was not sent because it would get us banned.');
			return;
		}
		
		$this->reply("\x02[OmegleSpy]\x02 <".$name."> ".$this->data['text']);
	}
	
	private function addSpy($Target) {
		if(isset($this->spies[$Target->id])) {
			$this->reply('There\'s already a spy running here.');
			return;
		}
		
		$this->spies[$Target->id] = $spy = array(
			'Target' => $this->Channel ? $this->Channel : $this->User,
			'Alice'  => new Omegle,
			'Bob'    => new Omegle
		);
		
		$check1 = $spy['Alice']->start();
		$check2 = $spy['Bob']->start();
		
		if(!$check1 || !$check2) {
			$this->reply('Error while connecting');
			$this->removeSpy($Target);
			return false;
		}
		
		$this->reply('Eavesdropping started, stop with !stopspy. Inject messages with !alice & !bob');
		$this->interval = 1;
	
	return true;
	}
	
	private function removeSpy($Target) {
		if(!isset($this->spies[$Target->id])) {
			$Target->privmsg('There is no spy running here.');
			return;
		}
		
		unset($this->spies[$Target->id]);
		$Target->privmsg('Spy stopped');
		if(empty($this->spies)) $this->interval = 0;
	}
	
}

?>
