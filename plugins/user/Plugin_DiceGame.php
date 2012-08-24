<?php

class Plugin_DiceGame extends Plugin {
	
	public $triggers = array('!dice');
	public $usage = 'start|stop|join|roll|stand|rules|(stats [player])';
	
	public $helpCategory = 'Games';
	public $helpText = "A game with dice. Type \x02!dice rules\x02 to see the rules.";
	
	private $game;
	
	function isTriggered() {
		if(!$this->Channel) {
			$this->reply('You can only play in a channel.');
			return;
		}
		
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}
		
		$this->loadGamestate();
		
		$parts = explode(' ', $this->data['text'], 2);
		
		switch($parts[0]) {
			case 'start':
				switch($this->game['state']) {
					case 'stopped':
						$this->initGame();
					break;
					case 'initializing':
						if(sizeof($this->game['players']) >= 2) {
							$this->game['state'] = 'running';
							$this->endTurn();
						} else {
							$this->reply('This game requires at least 2 players.');
						}
					break;
					default:
						$this->reply('There is already a game running.');
					break;
				}
			break;
			case 'stop':
				switch($this->game['state']) {
					case 'stopped':
						$this->reply('There is no game running.');
					break;
					default:
						if(!$this->playerExists($this->User->nick)) {
							$this->reply('You\'re not participating in the game.');
							return;
						}
						
						$this->reset();
						$this->reply('The game has been stopped.');
					break;
				}
			break;
			case 'join':
				switch($this->game['state']) {
					case 'initializing':
						if($this->addPlayer($this->User->nick)) {
							$this->reply($this->User->nick." joined the game. Start the game with \x02".$this->data['trigger']." start\x02 or wait for other players to \x02join\x02.");
						} else {
							$this->reply('You already joined.');
						}
					break;
					default:
						$this->reply('The game is not open for joining at the moment.');
					break;
				}
			break;
			case 'roll': case 'stand':
				switch($this->game['state']) {
					case 'running':
					case 'lastturn':
						if(!$this->playerExists($this->User->nick)) {
							$this->reply('You\'re not participating in the game.');
							return;
						}
						
						if($this->game['players'][$this->game['turn']]['nick'] != $this->User->nick) {
							$this->reply($this->User->nick.': It\'s not your turn.');
							return;
						}
						
						switch($this->data['text']) {
							case 'roll':
								$this->roll();
							break;
							case 'stand':
								$this->stand();
							break;
						}
						
						if($this->game['state'] == 'finished') {
							$this->endGame();
						}
					break;
					default:
						$this->reply('The game isn\'t running yet.');
					break;
				}
			break;
			case 'stats':
				if(isset($parts[1])) {
					$this->printPlayerStats($parts[1]);
				} else {
					$this->printStats();
				}
			break;
			case 'rules':
				$this->reply("\x02[Dice Game]\x02 Rules: Be the first to get 50 or more points on your permanent score by \x02roll\x02ing as many dice as you want one at a time. If you roll 1-5, the points get added to your temporary score. If you roll a 6 you lose all points on your temporary score. You can \x02stand\x02 any time, which adds your temporary score to your permanent score and your opponent gets the turn. If a player gets 50 or more points and stands, the other players have still one chance and can roll dice, trying to beat the score. ");
			break;
			default:
				$this->printUsage();
			break;
		}
		
		
		
		$this->saveGamestate();
		
	}
	
	private function reset() {
		$this->Channel->saveVar('dicegame_gamestate', array(
			'state' => 'stopped',
			'players' => array(),
			'turn' => false
		));
		$this->loadGamestate();
	}
	
	private function initGame() {
		$this->game['state'] = 'initializing';
		$this->addPlayer($this->User->nick);
		$this->reply("Dice game has been started. Waiting for other players - Type \x02".$this->data['trigger']." join\x02 to join the game.");
	}
	
	private function addPlayer($nick) {
		if($this->playerExists($nick)) return false;
		
		$this->game['players'][] = array(
			'nick'        => $nick,
			'points'      => 0,
			'temp_points' => 0,
			'alive'       => true
		);
	
	return true;
	}
	
	private function endTurn() {
		$this->game['turn'] = $this->getNextTurn();
		
		if($this->game['turn'] !== false) {
			$this->announceTurn();
		} else {
			$this->endGame();
		}
	}
	
	private function getNextTurn() {
		if($this->game['turn'] === false) {
			return array_rand($this->game['players']);
		} else {
			for($x=0,$l=sizeof($this->game['players']);$x<$l-1;$x++) {
				$i = ($x+$this->game['turn']+1) % $l;
				if($this->game['players'][$i]['alive']) return $i;
			}
		}
	
	return false;
	}
	
	private function announceTurn() {
		$next = $this->game['players'][$this->game['turn']]['nick'];
		
		$text = '';
		foreach($this->game['players'] as $player) {
			$text.= libIRC::noHighlight($player['nick']).': '.$player['points'].' points, ';
		}
		
		$text = substr($text, 0, -2).'.';
		
		$this->reply($next.'\'s turn. '.$text);
	}
	
	private function endGame() {
		$winner = $this->getLeader();
		
		$losers = array();
		foreach($this->game['players'] as $player) {
			if($player['nick'] == $winner['nick']) continue;
			$losers[] = $player['nick'].' ('.$player['points'].' points)';
		}
		
		$this->reply(sprintf(
			"\x02[Dice Game]\x02 %s won with %d points against %s.",
				$winner['nick'],
				$winner['points'],
				implode(' and ', $losers)
		));
		
		$this->saveStats();
		
		$this->reset();
	}
	
	private function roll() {
		$player = &$this->game['players'][$this->game['turn']];
		
		$dice = mt_rand(1, 6);
		
		$text = $player['nick'].' rolls a '.$dice.'.';
		
		if($dice != 6) {
			$player['temp_points']+= $dice;
			
			if($this->game['state'] == 'lastturn' && sizeof($this->getAliveNicks()) == 1) {
				$leader = $this->getLeader();
				if($player['points'] + $player['temp_points'] > $leader['points']) {
					$player['alive'] = false;
					$this->reply($text);
					$this->stand();
					return;
				}
			}
			
			$text.= ' Points: '.$player['points'].' + '.$player['temp_points'].' => '.($player['points']+$player['temp_points'])." - \x02roll\x02 again or \x02stand\x02?";
			$this->reply($text);
		} else {
			switch($this->game['state']) {
				case 'lastturn':
					$text.= ' You lost :(';
					$player['temp_points'] = 0;
					$player['alive'] = false;
				break;
				default:
					if(!$player['temp_points']) {
						$text.= ' What a pity :(';
					} else {
						$text.= ' You lose all your '.$player['temp_points'].' temporary points.';
						$player['temp_points'] = 0;
					}
				break;
			}
			
			$this->reply($text);
			$this->endTurn();
		}
	}
	
	private function stand() {
		$player = &$this->game['players'][$this->game['turn']];
		
		if($this->game['state'] == 'lastturn') {
			$leader = $this->getLeader();
			if($player['points']+$player['temp_points'] <= $leader['points']) {
				$this->reply("It is a really bad idea to \x02stand\x02 now.");
				return;
			}
		}
		
		if(!$player['temp_points']) {
			$this->reply("You must at least \x02roll\x02 once.");
			return;
		}
		
		$player['points']+= $player['temp_points'];
		$text = sprintf(
			"%d points got added to your permanent score. You now have %d points.",
				$player['temp_points'],
				$player['points']
		);
		$player['temp_points'] = 0;
		
		if($this->game['state'] == 'lastturn') {
			$text.= " You beat ".$leader['nick']." who only had ".$leader['points']." points.";
		}
		
		if($player['points'] >= 50) {
			$player['alive'] = false;
			
			$alive = $this->getAliveNicks();
			
			if(!empty($alive)) {
				$text.= sprintf(" %s %s one more chance to beat your score.",
					implode(' and ', $alive),
					sizeof($alive) == 1 ? 'gets' : 'get'
				);
			}
			
			if(sizeof($alive) == sizeof($this->game['players'])-1) { // If he's the first to finish
				$this->game['state'] = 'lastturn';
			}
		}
		
		$this->reply($text);
		$this->endTurn();
	}
	
	private function getLeader() {
		$leader = false;
		
		foreach($this->game['players'] as $player) {
			if($leader === false || $player['points'] > $leader['points']) {
				$leader = $player;
			}
		}
	
	return $leader;
	}
	
	private function getAliveNicks() {
		$alive = array();
		foreach($this->game['players'] as $player) {
			if($player['alive']) $alive[] = $player['nick'];
		}
		
	return $alive;
	}
	
	private function playerExists($nick) {
		foreach($this->game['players'] as $player) {
			if($player['nick'] == $nick) return true;
		}
	
	return false;
	}
	
	private function saveStats() {
		$winner = $this->getLeader();
		$players = $this->game['players'];
		$losers = array();
		foreach($players as $player) {
			if($winner['nick'] != $player['nick']) $losers[] = $player;
		}
		
		$this->saveVar('stats_games_completed', $this->getVar('stats_games_completed')+1);
		
		$max_players = $this->getVar('stats_max_players');
		if(!$max_players || sizeof($players) > $max_players['count']) {
			$new_max_players = array('count' => sizeof($players), 'players' => array(), 'date' => time());
			foreach($players as $player) {
				$new_max_players['players'][] = $player['nick'];
			}
			
			$this->reply(sprintf("A new largest game record has been achieved. %d players (%s). Old one was %d players (%s) %s ago",
				$new_max_players['count'],
				implode(', ', $new_max_players['players']),
				sizeof($max_players['count']),
				implode(', ', $max_players['players']),
				libTime::secondsToString($new_max_players['date'] - $max_players['date'])
			));
			
			$this->saveVar('stats_max_players', $new_max_players);
		}
		
		$max_points = $this->getVar('stats_max_points');
		
		if($max_points === false || $winner['points'] > $max_points['points']) {
			
			$this->reply(sprintf("You broke the highest sum record with %d points. Old one was %s with %d points %s ago.",
				$winner['points'],
				$max_points['nick'],
				$max_points['points'],
				libTime::secondsToString(time() - $max_points['time'])
			));
			
			$this->saveVar('stats_max_points', array(
				'nick'   => $winner['nick'],
				'points' => $winner['points'],
				'time'   => time()
			));
		}
		
		$ranking = $this->getVar('ranking');
		if($ranking === false) $ranking = array();
		
		$put_won = false;
		foreach($players as $player) {
			$check = false;
			foreach($ranking as &$rank) {
				if($rank['nick'] == $player['nick']) {
					$rank['played']++;
					$rank['last_played'] = time();
					$check = true;
				}
			
				if(!$put_won && $rank['nick'] == $winner['nick']) {
					$rank['won']++;
					$put_won = true;
				}
			}
			if(!$check) {
				$ranking[] = array(
					'nick' => $player['nick'],
					'played' => 1,
					'won' => $winner['nick'] == $player['nick'] ? 1 : 0,
					'last_played' => time()
				);
			}
		}
		
		usort($ranking, array('self', 'sortByWon'));
		$this->saveVar('ranking', $ranking);
		
	}
	
	private function printStats() {
		if(!$this->getVar('stats_games_completed')) {
			$this->reply('No one has ever played the game.');
			return;
		}
		
		$max_players = $this->getVar('stats_max_players');
		foreach($max_players['players'] as &$player) {
			$player = libIRC::noHighlight($player);
		}
		
		$max_points = $this->getVar('stats_max_points');
		
		$ranking = $this->getVar('ranking');
		$top5 = array();
		for($i=0;$i<5&&$i<sizeof($ranking);$i++) {
			$top5[] = libIRC::noHighlight($ranking[$i]['nick']).' ('.$ranking[$i]['won'].')';
		}
		$top5 = implode(', ', $top5);
		
		$this->reply(sprintf("%s %s been completed. The largest game ever was played by %d players (%s) on %s. The highest sum ever was achieved by \x02%s\x02 on %s with %d points. Top5: %s",
			libString::plural('dice game', $this->getVar('stats_games_completed')),
			libString::plural('have', $this->getVar('stats_games_completed')),
			
			$max_players['count'],
			implode(', ', $max_players['players']),
			date('Y-m-d H:i:s', $max_players['date']),
			
			libIRC::noHighlight($max_points['nick']),
			date('Y-m-d H:i:s', $max_points['time']),
			$max_points['points'],
			
			$top5
		));
	}
	
	private function printPlayerStats($nick) {
		$ranking = $this->getVar('ranking');
		if($ranking === false) $ranking = array();
		$stats = false;
		
		if(preg_match('/[^0-9]/', $nick)) {
			foreach($ranking as $index => $rank) {
				if($rank['nick'] == $nick) {
					$stats = $rank;
					$stats['rank'] = $index+1;
					break;
				}
			}
			
			if($stats === false) {
				$this->reply($nick.' has never played dice.');
				return;
			}
		} else {
			if(isset($ranking[$nick-1])) {
				$stats = $ranking[$nick-1];
				$stats['rank'] = $nick;
			} else {
				$this->reply("No one is on that rank.");
				return;
			}
		}
	
		$text = sprintf("%s has played %s, won %d and lost %d. %s is on rank %d of %d. %s's last game was %s ago.",
			$stats['nick'],
			libString::plural('game', $stats['played']),
			$stats['won'],
			$stats['played'] - $stats['won'],
			$stats['nick'],
			$stats['rank'],
			sizeof($ranking),
			$stats['nick'],
			libTime::secondsToString(time() - $stats['last_played'])
		);
		
		if($stats['rank'] != 1) {
			$text.= sprintf(" %s needs to win %s to rankup.",
				$stats['nick'],
				libString::plural('more game', ($ranking[$stats['rank']-2]['won'] - $stats['won'])+1)
			);
		}
		
		$this->reply($text);
		
	}
	
	private function loadGamestate() {
		$this->game = $this->Channel->getVar('dicegame_gamestate');
		if(!$this->game) $this->reset();
	}
	
	private function saveGamestate() {
		$this->Channel->saveVar('dicegame_gamestate', $this->game);
	}
	
	private function sortByWon($a, $b) {
		if($a['won'] == $b['won']) return 0;
	
	return $a['won'] > $b['won'] ? -1 : 1;
	}
	
}

?>
