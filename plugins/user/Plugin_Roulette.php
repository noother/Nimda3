<?php

class Plugin_Roulette extends Plugin {
	
	public $triggers = array('!roulette');
	
	private $game;
	
	
	function isTriggered() {
		if($this->data['isQuery']) {
			$this->reply('You can only play in a channel.');
			return;
		}
		
		if(isset($this->data['text'])) {
			$parts = explode(' ', $this->data['text'], 2);
			switch($parts[0]) {
				case 'stats':
					if(sizeof($parts) == 1) {
						$this->printStats();
					} else {
						$this->printPlayerStats($parts[1]);
					}
				break;
				default:
					$this->printUsage();
				break;
			}
		} else {
			$this->playGame();
		}
	}
	
	private function printUsage() {
		$this->reply('Usage: '.$this->data['trigger'].' [stats [nick]]');
	}
	
	private function initGame() {
		$this->game = array(
			'chambers'       => 6,
			'currentChamber' => 1,
			'badChamber'     => mt_rand(1, 7),
			'lastPlayer'     => '',
			'players'        => array()
		);
	}
	
	private function playGame() {
		$this->restoreGame();
		
		if($this->game['lastPlayer'] == $this->User->nick) {
			$this->reply('You can\'t pull the trigger twice in a row, dolt!');
			return;
		}
		
		$this->pullTrigger();
	}
	
	private function pullTrigger() {
		if(!isset($this->game['players'][$this->User->nick])) {
			$this->addPlayer($this->User->nick);
		}
		
		if($this->game['currentChamber'] == 1) {
			$this->game['players'][$this->User->nick]['started'] = true;
		}
		
		$output = $this->User->nick.': chamber #'.$this->game['currentChamber'].' of '.$this->game['chambers'].' => ';
		$endgame = false;
		
		if($this->game['currentChamber'] == $this->game['badChamber']) {
			$output.= '+BANG+';
			$this->game['players'][$this->User->nick]['lost'] = true;
			$endgame = true;
		} elseif($this->game['currentChamber'] == $this->game['chambers'] && $this->game['currentChamber'] != $this->game['badChamber']) {
			$output.= '+click+ wtf!?';
			$this->game['players'][$this->User->nick]['clicks']++;
			$endgame = true;
		} else {
			$output.= '+click+';
			$this->game['players'][$this->User->nick]['clicks']++;
		}
		
		$this->reply($output);
		
		if($endgame) {
			$this->endGame();
		} else {
			$this->game['currentChamber']++;
			$this->game['lastPlayer'] = $this->User->nick;
			$this->saveGame();
		}
	}
	
	
	private function endGame() {
		$this->saveScore();
		$this->Channel->action('reloads');
		$this->deleteGame();
	}
	
	
	
	private function addPlayer($nick) {
		$this->game['players'][$nick] = array(
			'started' => false,
			'clicks' => 0,
			'lost'  => false
		);
	}
	
	private function saveScore() {
		$serverchannel = addslashes($this->Server->id.':'.$this->Channel->id);
		
		foreach($this->game['players'] as $nick => $data) {
			$name = addslashes($nick);
			
			if(!$this->MySQL->fetchColumn("SELECT 1 FROM `roulette` WHERE `serverchannel` = '".$serverchannel."' AND `nick` = '".$nick."'")) {
				$this->MySQL->query("INSERT INTO `roulette` (`serverchannel`, `nick`) VALUES ('".$serverchannel."', '".$nick."')");
			}
			
			$sql = "
				UPDATE
					`roulette`
				SET
					`played` = `played` + 1,
					`started` = `started` + ".($data['started']?'1':'0').",
					`lost` = `lost` + ".($data['lost']?'1':'0').",
					`clicks` = `clicks` + ".$data['clicks'].",
					`last_played` = NOW()
				WHERE
					`serverchannel` = '".$serverchannel."'
					AND `nick` = '".$nick."'
			";
			$this->MySQL->query($sql); 
			
		}
	}
	
	private function printStats() {
		$serverchannel = addslashes($this->Server->id.':'.$this->Channel->id);
		
		$sql = "
			SELECT
				SUM(`started`) AS `played`,
				SUM(`clicks`) + SUM(`lost`) AS `shots`,
				COUNT(*) AS `playercount`
			FROM
				`roulette`
			WHERE
				`serverchannel` = '".$serverchannel."'
		";
		
		$stats = $this->MySQL->fetchRow($sql);
		if(!$stats['playercount']) {
			$this->reply('No games have been played yet.');
			return;
		}
		
		$sql = "SELECT
					`nick`,
					`clicks` / (`clicks`+`lost`) * 100 AS `percentage`
				FROM
					`roulette`
				WHERE
					`serverchannel` = '".$serverchannel."'
					
				GROUP BY
					`nick`
				ORDER BY
					`percentage` DESC
				";
		$res = $this->MySQL->query($sql);
		
		if(empty($res)) {
			$luckiest   = array('nick' => 'no one', 'percentage' => 100);
			$unluckiest = array('nick' => 'no one', 'percentage' => 0);
		} else {
			$luckiest   = $res[0];
			$unluckiest = array_pop($res);
		}
		
		$this->reply(sprintf(
			"\x02Roulette stats:\x02 %s completed, %s fired at %s. Luckiest: %s (%.2f%% clicks). Unluckiest: %s (%.2f%% clicks).",
				libString::plural('game', $stats['played']),
				libString::plural('shot', $stats['shots']),
				libString::plural('player', $stats['playercount']),
				$luckiest['nick'],
				$luckiest['percentage'],
				$unluckiest['nick'],
				$unluckiest['percentage']
		));
		
		
		
		
		
		
	}
	
	private function printPlayerStats($nick) {
		$serverchannel = $this->Server->id.':'.$this->Channel->id;
		
		$data = $this->MySQL->fetchRow("SELECT * FROM `roulette` WHERE serverchannel ='".addslashes($serverchannel)."' AND `nick` = '".addslashes($nick)."'");
		if(!$data) {
			$this->reply($nick.' has never played roulette.');
			return;
		}
		
		$this->reply(sprintf('%s has played %s, won %d and lost %d. %s started %s, pulled the trigger %s and found the chamber empty on %s.',
			$data['nick'],
			libString::plural('game', $data['played']),
			$data['played']-$data['lost'],
			$data['lost'],
			$data['nick'],
			libString::plural('game', $data['started']),
			libString::plural('time', $data['clicks']+$data['lost']),
			libString::plural('occasion', $data['clicks'])
		));	
	}
	
	
	
	
	
	private function restoreGame() {
		$this->game = $this->Channel->getVar('roulette_gamedata');
		
		if($this->game === false) {
			$this->initGame();
		}
	}
	
	private function saveGame() {
		$this->Channel->saveVar('roulette_gamedata', $this->game);
	}
	
	private function deleteGame() {
		$this->Channel->removeVar('roulette_gamedata');
	}
	
}

?>
