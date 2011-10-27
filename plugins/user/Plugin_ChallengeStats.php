<?php

class Plugin_ChallengeStats extends Plugin {
	
	private $links;
	
	function onLoad() {
		require_once('ChallengeStats/ChallengeStats.php');
		
		$files = libFilesystem::getFiles('plugins/user/ChallengeStats/sites/', 'php');
		foreach($files as $file) {
			require_once('ChallengeStats/sites/'.$file);
			
			$classname = substr($file, 0, -4);
			$Class     = new $classname($this);
			foreach($Class->triggers as $trigger) {
				$this->triggers[]      = $trigger;
				$this->links[$trigger] = $classname;
			}
		}
	}
	
	function isTriggered() {
		$username  = isset($this->data['text']) ? $this->data['text'] : $this->User->nick;
		$classname = $this->links[$this->data['trigger']];
		
		$this->addJob('getStatsString', array(
			'username'  => $username,
			'classname' => $classname,
			'cache_dir' => $this->Bot->getTempDir().'/cache'
		));
	}
	
	function onJobDone() {
		$this->reply($this->data['result']);
	}
	
	function getStatsString($data) {
		require_once('ChallengeStats/ChallengeStats.php');
		require_once('ChallengeStats/sites/'.$data['classname'].'.php');
		
		$Plugin = new $data['classname']($data['cache_dir']);
		
	return $Plugin->getData($data['username']);	
	}
	
}

?>
