<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use Nimda\Plugin\User\ZMachine\ZMachine;
use noother\Library\Strings;

class ZMachinePlugin extends Plugin {
	
	public $triggers = array('!zmachine', '!adventure', '!z');
	
	public $helpTriggers = array('!zmachine');
	public $helpText = 'Play Z-Machine text adventure games in IRC! If you play in query, you don\'t need a trigger to issue commands. This is best played on IRC servers without flood control.';
	public $helpCategory = 'Games';
	public $usage = '[list|update|(start|show <game>)|<command>]';
	public $interval = 0; // gets changed when a session starts or no sessions remain
	
	private $sessions = array();
	private $games = array();
	
	const FILEDIR = __DIR__.'/ZMachine/files';
	const MAX_SESSIONS = 100;
	const MAX_IDLE_TIME = 43200; // 12h
	
	function onLoad() {
		$this->updateGames();
	}
	
	function onUnload() {
		foreach($this->sessions as $session_id => $session) {
			$this->autosave($session_id);
		}
	}
	
	function isTriggered() {
		$session_id = $this->getSessionId();
		if(isset($this->sessions[$session_id])) {
			$this->sendCommand($session_id, $this->data['text']);
			return;
		}
		
		
		$param = false;
		if(!isset($this->data['text'])) {
			$command = 'list';
		} else {
			$tmp = explode(' ', $this->data['text'], 2);
			$command = $tmp[0];
			if(isset($tmp[1])) $param = $tmp[1];
		}
		
		switch($command) {
			case 'list':
				$this->showAvailableGames();
			break;
			case 'show':
				$this->showDescription($param);
			break;
			case 'start':
				$this->startSession($session_id, $param);
			break;
			case 'update':
				$additions = $this->updateGames();
				if(empty($additions)) {
					$this->reply('Nothing changed.');
				} else {
					$this->reply(sprintf("The following games got added: %s",
						implode(', ', $additions)
					));
				}
			break;
			default:
				$this->printUsage();
			break;
		}
	}
	
	function onQuery() {
		if(empty($this->sessions)) return;
		if($this->data['text'][0] == '!') return;
		
		$session_id = $this->getSessionId();
		if(!isset($this->sessions[$session_id])) return;
		
		$this->sendCommand($session_id, $this->data['text']);
	}
	
	function onInterval() {
		foreach($this->sessions as $session => $data) {
			$this->process($session);
			
			if($this->Bot->time > $data['last_action']+self::MAX_IDLE_TIME) {
				$data['Target']->privmsg("Stopping your \x02".$data['game_name']."\x02 session due to inactivity.");
				$this->autosave($session);
				$this->removeSession($session);
			}
		}
	}
	
	private function updateGames() {
		$files = glob(self::FILEDIR.'/*.conf');
		$additions = [];

		foreach($files as $file) {
			$config = parse_ini_file($file);
			if(file_exists(self::FILEDIR.'/games/'.$config['gamefile'])) {
				if(!isset($this->games[$config['id']])) $additions[] = $config['name'];
				$this->games[$config['id']] = $config;
			}
		}
		
	return $additions;
	}
	
	function findGame($search) {
		$search = strtolower($search);
		foreach($this->games as $game) {
			if(strtolower($game['id']) == $search || strtolower($game['name']) == $search) return $game;
		}
		
	return false;
	}
	
	private function showAvailableGames() {
		foreach($this->games as $id => $data) {
			$games[] = $data['name'];
		}
		sort($games);
		
		$this->reply("\x02Available Z-Machine games\x02: ".implode(', ', $games));
	}
	
	private function showDescription($game) {
		if(false === $info = $this->findGame($game)) {
			$this->reply('This game doesn\'t exist.');
			return;
		}
		
		$this->reply("\x02".$info['name']."\x02".': '.$info['description']);
	}
	
	private function getSessionId() {
		return $this->Server->id.':'.($this->Channel ? $this->Channel->id : $this->User->id);
	}
	
	private function startSession($session_id, $game) {
		if(false === $info = $this->findGame($game)) {
			$this->reply('This game doesn\'t exist.');
			return;
		}
		
		if(sizeof($this->sessions) >= self::MAX_SESSIONS) {
			$this->reply('There are already '.self::MAX_SESSIONS.' sessions running..');
			return false;
		}
		
		$this->reply("Starting \x02".$info['name']."\x02.".($this->Channel ? ' Use !z <command> to interact.' : ''). " Typical commands are look, look at the mailbox, open mailbox, get leaflet, read leaflet, go south, etc. | Special commands are \x02save\x02, \x02load\x02, & \x02RETURN\02");
		
		if(empty($this->sessions)) $this->interval = 1;
		$this->sessions[$session_id] = array(
			'Target' => $this->Channel ? $this->Channel : $this->User,
			'Game' => new ZMachine(self::FILEDIR.'/games/'.$info['gamefile']),
			'game_id' => $info['id'],
			'game_name' => $info['name'],
			'last_action' => $this->Bot->time
		);
		
		if(!$this->sessions[$session_id]['Game']->isReady) {
			$this->reply('Something went wrong while starting the game. Is dfrotz installed?');
			$this->removeSession($session_id);
			return false;
		}
		
		
		$s = &$this->sessions[$session_id];
		
		$this->loadAutosave($session_id);
		$this->process($session_id);
		
	return true;
	}
	
	private function removeSession($session_id, $show=true) {
		$data = &$this->sessions[$session_id];
		unset($this->sessions[$session_id]);
		if(empty($this->sessions)) $this->interval = 0;
		
		if($show) $data['Target']->privmsg("Your \x02".$data['game_name']."\x02 session has been terminated.");
	}
	
	private function process($session_id) {
		$data = &$this->sessions[$session_id];
		$Game = $data['Game'];
		$Target = $data['Target'];
		
		$messages = $Game->getData();
		
		foreach($messages as $msg) {
			switch($msg['type']) {
				case 'text':
					if($msg['text'] == '') $msg['text'] = "\xc2\xa0"; // whitespace
					$Target->privmsg($msg['text']);
				break;
				
				case 'location_info':
					$Target->privmsg("\x02".$msg['text']."\x02");
				break;
				
				case 'score_change':
					if($msg['score'] > $msg['old_score']) {
						$Target->privmsg(sprintf("You gained \x02%d\x02 points and have a total of \x02%d\x02 points now.",
							$msg['score']-$msg['old_score'],
							$msg['score']
						));
						
						$this->updatePersonalHighscore($session_id, $msg['score'], $msg['moves']);
						$this->updateGlobalHighscore($session_id, $msg['score'], $msg['moves']);
					} else {
						$Target->privmsg(sprintf("You lost \x02%d\x02 points and have a total of \x02%d\x02 points now.",
							$msg['old_score']-$msg['score'],
							$msg['score']
						));
					}
				break;
				
				case 'close':
					$this->removeSession($session_id);
				break 2;
			}
		}
	}
	
	private function updatePersonalHighscore($session_id, $score, $moves) {
		$data = &$this->sessions[$session_id];
		
		$var = 'personalhighscore_'.$data['Target']->id.'_'.$data['game_id'];
		$cur = $this->getVar($var, array('score' => 0, 'moves' => 0));
		if(($score > $cur['score']) || ($score == $cur['score'] && $moves < $cur['moves'])) {
			$this->saveVar($var, array('score' => $score, 'moves' => $moves));
		}
	}
	
	private function updateGlobalHighscore($session_id, $score, $moves) {
		$data = &$this->sessions[$session_id];
		
		$var = 'globalhighscore_'.$data['game_id'];
		$cur = $this->getVar($var, array('name' => 'no one', 'score' => 0, 'moves' => 0));
		
		if(($score > $cur['score']) || ($score == $cur['score'] && $moves < $cur['moves'])) {
			$this->saveVar($var, array(
				'name' => $data['Target']->name,
				'score' => $score,
				'moves' => $moves,
				'time' => $this->Bot->time
			));
			
			if($data['Target']->name != $cur['name']) {
				$data['Target']->privmsg(sprintf("\x02You've just beaten the highscore for %s which was %d points in %d moves held by %s!\x02",
					$data['game_name'],
					$cur['score'],
					$cur['moves'],
					$cur['name']
				));
			}
		}
	}
	
	private function sendCommand($session_id, $command) {
		if($command != 'ENTER' && $command != 'RETURN') $command = strtolower($command);
		
		$s = &$this->sessions[$session_id];
		$G = $s['Game'];
		$T = $s['Target'];
		
		$tmp = str_replace(',', '.', $command);
		$tmp = explode('.', $tmp);
		if(sizeof($tmp) > 1) {
			$blacklist = array('save', 'restore', 'load', 'restart', 'reset', 'script', 'unscript', 'quit', 'q', 'die');
			foreach($tmp as $pcmd) {
				$pcmd = trim($pcmd);
				if(in_array($pcmd, $blacklist)) {
					$T->privmsg("Please don't use \x02".$pcmd."\x02 in a command chain.");
					return;
				}
			}
		}
		
		switch($command) {
			case 'save':
				$G->write('save');
				$G->write('../saves/'.$this->getSaveName($session_id));
				$G->write('y');
				usleep(50000);
				$G->getData();
				
				$T->privmsg("Your \x02".$s['game_name']."\x02 session has been saved.");
			break;
			
			case 'restore': case 'load':
				if(file_exists(self::FILEDIR.'/saves/'.$this->getSaveName($session_id))) {
					$G->write('restore');
					$G->write('../saves/'.$this->getSaveName($session_id));
					usleep(50000);
					$G->getData();
					
					$T->privmsg("Your \x02".$s['game_name']."\x02 session has been loaded.");
					$T->privmsg("\xc2\xa0");
					$G->write('look');
				} else {
					$T->privmsg('You don\'t have a savegame yet.');
				}
			break;
			
			case 'RETURN': case 'ENTER':
				$G->write('');
			break;
			
			case 'script': case 'unscript':
				$T->privmsg('Unsupported.');
			break;
			
			default:
				$G->write($command);
			break;
		}
		
		usleep(5000); // Wait 5ms for the process to handle our message & send output - if it's not fast enough, output will be send to IRC on next timer interval
		$this->process($session_id);
		
		$s['last_action'] = $this->Bot->time;
	}
	
	private function autosave($session_id) {
		$s = &$this->sessions[$session_id];
		$G = $s['Game'];
		
		$G->write('save');
		$G->write('../saves/auto_'.$this->getSaveName($session_id));
		$G->write('y');
		usleep(50000);
		$G->getData();
		
		$s['Target']->privmsg("Your \x02".$s['game_name']."\x02 session has been autosaved.", true);
	}
	
	private function getSaveName($session_id) {
		$name = $this->sessions[$session_id]['game_id'].':'.$session_id;
		$file = Strings::normalizeString($name).'_'.crc32($name).'.sav';
		
	return $file;
	}
	
	private function loadAutosave($session_id) {
		$filename = 'auto_'.$this->getSaveName($session_id);
		if(!file_exists(self::FILEDIR.'/saves/'.$filename)) return false;
		 
		$s = &$this->sessions[$session_id];
		$G = $s['Game'];
		
		for($i=0;$i<10;$i++) {
			$G->write('');
		}
		$G->write('restore');
		$G->write('../saves/'.$filename);
		usleep(50000);
		$G->getData();
		
		$s['Target']->privmsg('Your autosave has been autoloaded. \\o/');
		$s['Target']->privmsg("\xc2\xa0");
		unlink(self::FILEDIR.'/saves/'.$filename);
		
		$this->sendCommand($session_id, 'look');
		
	return true;
	}
	
	
}

?>
