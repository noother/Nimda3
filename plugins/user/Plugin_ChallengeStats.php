<?php

class Plugin_ChallengeStats extends Plugin {
	
	private $links;
	
	function onLoad() {
		require_once('ChallengeStats/ChallengeStats.php');
		
		$files = libFilesystem::getFiles('plugins/user/ChallengeStats/sites/', 'php');
		foreach($files as $file) {
			require_once('ChallengeStats/sites/'.$file);
			
			$classname = substr($file, 0, -4);
			$Class     = new $classname;
			foreach($Class->triggers as $trigger) {
				$this->triggers[]      = $trigger;
				$this->links[$trigger] = $Class;
			}
		}
	}
	
	function isTriggered() {
		$username = isset($this->data['text']) ? $this->data['text'] : $this->User->nick;
		$Class = $this->links[$this->data['trigger']];
		$this->reply($Class->getData($username));
	}
	
}

?>
