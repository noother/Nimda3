<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\Library\IRC;
use noother\Library\Strings;

class RoulettePlugin extends Plugin {
	
	public $triggers = array('!roulette');
	public $usage = '[[global]stats [nick]]';
	
	public $helpCategory = 'Games';
	public $helpText = "Russian roulette. Die or don't - with highscore";
	
	private $game;
	
	
	function isTriggered() {
		if($this->data['isQuery']) {
			$this->reply('You can only play in a channel.');
			return;
		}
		
		if(isset($this->data['text'])) {
			$parts = explode(' ', $this->data['text'], 2);
			switch($parts[0]) {
				case 'stats': case 'globalstats':
					if(sizeof($parts) == 1) {
						$this->printStats($parts[0] == 'globalstats');
					} else {
						$this->printPlayerStats($parts[1], $parts[0] == 'globalstats');
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
	
	private function printStats($global=false) {
		$serverchannel = addslashes($this->Server->id.':'.$this->Channel->id);
		
		$sql = "
			SELECT
				SUM(`started`) AS `played`,
				SUM(`clicks`) + SUM(`lost`) AS `shots`,
				COUNT(*) AS `playercount`
			FROM
				`roulette`
		";
		if(!$global) {
			$sql.= "
				WHERE
					`serverchannel` = '".$serverchannel."'
			";
		}
		
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
			";
		if(!$global) $sql.= "`serverchannel` = '".$serverchannel."' AND ";
		$sql.= "`played` >= 10
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
			"\x02Roulette %sstats:\x02 %s completed, %s fired at %s. Luckiest: %s (%.2f%% clicks). Unluckiest: %s (%.2f%% clicks).",
				$global ? "global" : "",
				Strings::plural('game', $stats['played']),
				Strings::plural('shot', $stats['shots']),
				Strings::plural('player', $stats['playercount']),
				IRC::noHighlight($luckiest['nick']),
				$luckiest['percentage'],
				IRC::noHighlight($unluckiest['nick']),
				$unluckiest['percentage']
		));
	}
	
	private function printPlayerStats($nick, $global=false) {
		$serverchannel = $this->Server->id.':'.$this->Channel->id;
		
		if($global) {
			$sql = "
				SELECT
					`nick`, SUM(`played`) AS `played`, SUM(`started`) AS `started`, SUM(`lost`) AS `lost`, SUM(`clicks`) AS `clicks`
				FROM
					`roulette`
				WHERE
					`nick` = '".addslashes($nick)."'
			";
		} else {
			$sql = "
				SELECT
					*
				FROM
					`roulette`
				WHERE
					`serverchannel` ='".addslashes($serverchannel)."'
					AND `nick` = '".addslashes($nick)."'
			";
		}
		
		
		
		
		$data = $this->MySQL->fetchRow($sql);
		if(!$data) {
			$this->reply($nick.' has never played roulette.');
			return;
		}
		
		$this->reply(sprintf('%s has played %s, won %d and lost %d. %s started %s, pulled the trigger %s and found the chamber empty on %s.',
			$data['nick'],
			Strings::plural('game', $data['played']),
			$data['played']-$data['lost'],
			$data['lost'],
			$data['nick'],
			Strings::plural('game', $data['started']),
			Strings::plural('time', $data['clicks']+$data['lost']),
			Strings::plural('occasion', $data['clicks'])
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
