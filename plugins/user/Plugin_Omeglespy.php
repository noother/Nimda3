<?php

require_once('Omegle/Omegle.php');

class Plugin_Omeglespy extends Plugin {
	
	public $triggers = array('!omeglespy', '!chatspy', '!stopspy');
	
	public $helpTriggers = array('!omeglespy');
	public $helpText = 'Lets you eavesdrop a conversation between two random users on omegle.com';
	
	private $spies = array();
	
	function isTriggered() {
		
		if($this->Channel) {
			$Target = $this->Channel;
		} else {
			$Target = $this->User;
		}
		
		switch($this->data['trigger']) {
			case '!omeglespy': case '!chatspy':
				$this->addSpy($Target);
			break;
			
			case '!stopspy':
				$this->removeSpy($Target);
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
							$OtherGuy->send($message);
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
		
		$spy['Alice']->start();
		$spy['Bob']->start();
		
		$this->reply('Eavesdropping started, stop with !stopspy.');
		$this->interval = 1;
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
