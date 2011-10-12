<?php

class Plugin_DiceGame extends Plugin {
	
	public $triggers = array('!dice');
	
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
		
		switch($this->data['text']) {
			case 'start':
				if($this->game['state'] != 'stopped') {
					$this->reply('There is already a game running.');
				} else {
					$this->initGame();
				}
			break;
			case 'stop':
				if($this->game['state'] == 'stopped') {
					$this->reply('There is no game running.');
				} else {
					$this->reset();
					$this->reply('The game has been stopped.');
				}
			break;
			case 'join':
				if($this->game['state'] != 'initializing') {
					$this->reply('The game is not open for joining at the moment.');
				} else {
					if($this->addPlayer($this->User->nick)) {
						$this->reply($this->User->nick.' joined the game.');
						$this->game['state'] = 'running';
						$this->game['players'][$this->User->nick]['turn'] = true;
						$this->announceTurn();
					} else {
						$this->reply('You already joined.');
					}
				}
			break;
			case 'roll': case 'stand':
				if($this->game['state'] != 'running' && $this->game['state'] != 'lastturn') {
					$this->reply('The game isn\'t running yet.');
				} elseif(!isset($this->game['players'][$this->User->nick])) {
					$this->reply('You\'re not participating in the game.');
				} elseif(!$this->game['players'][$this->User->nick]['turn']) {
					$this->reply($this->User->nick.': It\'s not your turn.');
				} else {
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
				}
			break;
			case 'rules':
				$this->reply("\x02[Dice Game]\x02 Rules: Be the first to get 50 or more points on your permanent score by \x02roll\x02ing as many dice as you want one at a time. If you roll 1-5, the points get added to your temporary score. If you roll a 6 you lose all points on your temporary score. You can \x02stand\x02 any time, which adds your temporary score to your permanent score and your opponent gets the turn. If a player gets 50 or more points and stands, the other player has still one chance and can roll dice, trying to beat the score. ");
			break;
			default:
				$this->printUsage();
			break;
		}
		
		
		
		$this->saveGamestate();
		
	}
	
	private function printUsage() {
		$this->reply('Usage: '.$this->data['trigger'].' start|stop|join|roll|stand|rules');
	}
	
	private function reset() {
		$this->Channel->saveVar('dicegame_gamestate', array(
			'state' => 'stopped',
			'players' => array(),
			'final_points' => 0
		));
		$this->loadGamestate();
	}
	
	private function initGame() {
		$this->game['state'] = 'initializing';
		$this->addPlayer($this->User->nick);
		$this->reply("Dice game has been started. Waiting for another player - Type \x02".$this->data['trigger']." join\x02 to join the game.");
	}
	
	private function addPlayer($nick) {
		if(isset($this->game['players'][$nick])) return false;
		
		$this->game['players'][$nick] = array(
			'points'      => 0,
			'temp_points' => 0,
			'turn'        => false
		);
	
	return true;
	}
	
	private function switchTurns() {
		foreach($this->game['players'] as &$data) {
			if($data['turn']) $data['turn'] = false;
			else              $data['turn'] = true;
		}
		$this->announceTurn();
	}
	
	private function announceTurn() {
		$text = '';
		foreach($this->game['players'] as $nick => $data) {
			if($data['turn']) {
				$next = $nick;
			}
			
			$text.= $nick.': '.$data['points'].' points, ';
		}
		
		$text = substr($text, 0, -2).'.';
		
		$this->reply($next.'\'s turn. '.$text);
	}
	
	private function endGame() {
		$winner = array('nick' => '', 'points' => 0);
		$loser  = array('nick' => '', 'points' => 99999);
		foreach($this->game['players'] as $nick => $data) {
			if($data['points'] > $winner['points']) {
				$winner['nick']   = $nick;
				$winner['points'] = $data['points'];
			}
			
			if($data['points'] < $loser['points']) {
				$loser['nick']    = $nick;
				$loser['points']  = $data['points'];
			}
		}
		
		$this->reply(sprintf(
			"\x02[Dice Game]\x02 %s won with %d points against %s who only had %d points.",
				$winner['nick'],
				$winner['points'],
				$loser['nick'],
				$loser['points']
		));
		$this->reset();
	}
	
	private function roll() {
		$data = &$this->game['players'][$this->User->nick];
		
		$dice = mt_rand(1, 6);
		
		$text = $this->User->nick.' rolls a '.$dice.'. ';
		
		if($dice != 6) {
			$data['temp_points']+= $dice;
			if($this->game['state'] == 'lastturn' && $data['points']+$data['temp_points'] > $this->game['final_points']) {
				$data['points']+= $data['temp_points'];
				$this->reply($text.'You beat your opponent. Congratulations!');
				$this->game['state'] = 'finished';
			} else {
				$text.= 'Points: '.$data['points'].' + '.$data['temp_points'].' => '.($data['points']+$data['temp_points'])." - \x02roll\x02 again or \x02stand\x02?";
				$this->reply($text);
			}
		} else {
			if($this->game['state'] == 'lastturn') {
				$data['temp_points'] = 0;
				$text.= 'You lost :(';
				$this->reply($text);
				$this->game['state'] = 'finished';
			} else {
				if(!$data['temp_points']) {
					$text.= 'What a pity :(';
				} else {
					$text.= 'You lose all your '.$data['temp_points'].' temporary points.';
					$data['temp_points'] = 0;
				}
				$this->reply($text);
				$this->switchTurns();
			}
		}
	}
	
	private function stand() {
		if($this->game['state'] == 'lastturn') {
			$this->reply("It is a really bad idea to \x02stand\x02 now.");
			return;
		}
		
		$data = &$this->game['players'][$this->User->nick];
		
		if(!$data['temp_points']) {
			$this->reply("You must at least \x02roll\x02 once.");
			return;
		}
		
		$data['points']+= $data['temp_points'];
		$text = sprintf(
			"%d points got added to your permanent score. You now have %d points.",
				$data['temp_points'],
				$data['points']
		);
		$data['temp_points'] = 0;
		
		if($data['points'] >= 50) {
			$text.= ' Your opponent gets one more chance to beat your score.';
			$this->game['state'] = 'lastturn';
			$this->game['final_points'] = $data['points'];
		}
		
		$this->reply($text);
		
		$this->switchTurns();
	}
	
	private function loadGamestate() {
		$this->game = $this->Channel->getVar('dicegame_gamestate');
		if(!$this->game) $this->reset();
	}
	
	private function saveGamestate() {
		$this->Channel->saveVar('dicegame_gamestate', $this->game);
	}
	
}

?>
