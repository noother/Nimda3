<?php

class Plugin_Popularity extends Plugin {
	
	public $triggers = array('!luv', '!luvs');
	public $helpTriggers = array('!luv');
	public $usage = '[nick]';
	public $helpText = '!luv <nick> Increases <nick>\'s popularity rating. !luvs <nick> shows <nick>\'s popularity rating. !luvs without an argument shows the Top10';
	
	function isTriggered() {
		switch($this->data['trigger']) {
			case '!luv':
				if(!isset($this->data['text'])) {
					$this->printUsage();
					return;
				}
				
				$this->increasePopularity($this->data['text']);
			break;
			case '!luvs':
				if(!isset($this->data['text'])) {
					$this->showRanking();
				} else {
					$this->showPopularity($this->data['text']);
				}
			break;
		}
	}
	
	function increasePopularity($nick) {
		if(!$this->Channel) {
			$this->reply('This command only works in a channel.');
			return;
		}
		
		$id = strtolower($nick);
		
		if(!isset($this->Channel->users[$id])) {
			$this->reply("This user is not here.");
			return;
		}
		
		if($id == $this->User->id) {
			$this->reply('You can\'t luv yourself!');
			return;
		}
		
		$luvs = $this->getVar('luvs');
		
		if(!isset($luvs[$id])) {
			$luvs[$id] = array(
				'id' => $this->Channel->users[$id]->id,
				'nick' => $this->Channel->users[$id]->nick,
				'luvs' => array()
			);
		}
		
		$luved = &$luvs[$id];
		
		if(in_array($this->User->id, $luved['luvs'])) {
			$this->reply('You already luved '.$luved['nick'].'.');
			return;
		}
		
		$luved['luvs'][] = $this->User->id;
		
		$this->saveVar('luvs', $luvs);
		$this->reply($this->User->nick.' has increased '.$luved['nick'].'\'s popularity rating. It is now '.sizeof($luved['luvs']).'.');
	}
	
	function showRanking() {
		$luvs = $this->getVar('luvs');
		if(!$luvs) {
			$this->reply('There are no luvs yet.');
			return;
		}
		
		usort($luvs, array('self', 'sortByLuvs'));
		
		$top10 = array();
		for($i=0;$i<10&&$i<sizeof($luvs);$i++) {
			$top10[] = libIRC::noHighlight($luvs[$i]['nick']).' ('.sizeof($luvs[$i]['luvs']).')';
		}
		$this->reply('Popularity ranking: '.implode(', ', $top10));
	}
	
	function showPopularity($nick) {
		$id = strtolower($nick);
		
		$luvs = $this->getVar('luvs');
		if(!isset($luvs[$id])) {
			$this->reply($nick.' didn\'t get any luvs yet. :(');
		} else {
			$this->reply($luvs[$id]['nick'].'\'s popularity rating is '.sizeof($luvs[$id]['luvs']).'.');
		}
	}
	
	private function sortByLuvs($a, $b) {
		if(sizeof($a['luvs']) == sizeof($b['luvs'])) return 0;
	return sizeof($a['luvs']) > sizeof($b['luvs']) ? -1 : 1;
	}
	
}

?>
