<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;

class ChallengeStatsPlugin extends Plugin {
	public $helpCategory = 'Challenges';
	public $helpTriggers = array();
	public $usage = '[username]';
	public $helpText = 'Prints your, or [username]\'s challenge status at %s';

	private $links;

	function onLoad() {
		$files = glob('src/Plugin/User/ChallengeStats/*.php');
		foreach($files as $file) {
			$info = pathinfo($file);
			if($info['filename'] == 'ChallengeStats') continue;

			require_once($file);
			$classname = 'Nimda\\'.str_replace('/', '\\', substr($file, 4, -4)); // remove src/ from the beginning & .php from the end
			$Class     = new $classname($this);

			$c = 0;
			foreach($Class->triggers as $trigger) {
				if($c++ == 0) $this->helpTriggers[] = $trigger;
				$this->triggers[]      = $trigger;
				$this->links[$trigger] = $classname;
			}
		}
	}

	function getHelpText() {
		$trigger = $this->data['trigger'];
		if(!isset($this->links[$trigger])) {
			$trigger = '!'.$trigger;
		}
		
		$Plugin = new $this->links[$trigger];
		
	return sprintf($this->helpText, $Plugin->getUrl());
	}
	
	function isTriggered() {
		$username  = isset($this->data['text']) ? $this->data['text'] : $this->User->nick;
		$classname = $this->links[$this->data['trigger']];
		
		$this->addJob('getStatsString', array(
			'username'  => $username,
			'classname' => $classname,
			'cache_dir' => 'tmp/cache'
		));
	}
	
	function onJobDone() {
		$this->reply($this->data['result']);
	}
	
	function getStatsString($data) {
		require_once(str_replace(['\\', 'Nimda/'], ['/', 'src/'], $data['classname']).'.php');

		$Plugin = new $data['classname']($data['cache_dir']);

	return $Plugin->getData($data['username']);	
	}
	
}

?>
