<?php

require_once('IRC_Channel.php');
require_once('IRC_User.php');



final class IRC_Server {
	
	private $socket;
	private $queueRead = array();
	private $queueSend = array();
	private $waitingForPong = false;
	private $estimatedRecvq = array();
	private $lastRecvqRemoved = 0;
	private $floodCooldown = false;
	private $reconnect = false;
	
	private $myData = array();
	
	public $Bot;
	public $id;
	public $channels  = array();
	public $users     = array();
	public $host         = '';
	public $port         = 0;
	public $isSSL        = false;
	public $Me;
	public $NickServ;
	public $lastLifeSign = 0;
	public $nickservIdentifyCommand = false;
	
	public function __construct($Bot, $name, $host, $port, $ssl=false) {
		$this->Bot          = $Bot;
		$this->id           = $name;
		$this->host         = $host;
		$this->port         = $port;
		$this->isSSL        = $ssl;
		
		if(!$this->getVar('estimated_CLIENT_FLOOD')) $this->saveVar('estimated_CLIENT_FLOOD', -1);
		if(!$this->getVar('estimated_RECVQ_SPEED')) $this->saveVar('estimated_RECVQ_SPEED', 1);
		
		$this->connect();
	}
	
	private function connect() {
		$this->socket = fsockopen(($this->isSSL?'ssl://':'').$this->host, $this->port);
		stream_set_blocking($this->socket, 0);
		
		$this->lastLifeSign = microtime(true);
	}
	
	private function reconnect() {
		$channels = array();
		foreach($this->channels as $channel => $crap) {
			$channels[] = $channel;
		}
		
		if($this->socket) {
			$this->quit('Connection lost - Reconnecting');
			fclose($this->socket);
		}
		$this->channels = array();
		$this->users    = array();
		$this->estimatedRecvq = array();
		
		$this->connect();
		
		if(isset($this->myData['pass'])) $this->setPass($this->myData['pass']);
		
		$this->setUser(
			$this->myData['username'],
			$this->myData['hostname'],
			$this->myData['servername'],
			$this->myData['realname']
		);
		
		$this->setNick($this->myData['nick']);
		
		foreach($channels as $channel) {
			$this->joinChannel($channel);
		}
		
		$this->reconnect = false;
	}
	
	private function read() {
		$message = fgets($this->socket);
		if(!$message) return false;
		while($message{strlen($message)-1} != "\n") $message.= fgets($this->socket);
		
		$message = trim($message);
		echo '>> '.$message."\n";
	return $message;
	}

	private function write($string) {
		echo '<< '.$string."\n";
		fputs($this->socket, $string."\r\n");
		$this->estimatedRecvq[] = array('msg' => $string, 'length' => strlen($string));
	}
	
	private function getUser($nick) {
		$id = strtolower($nick);
		if(!isset($this->users[$id])) $this->addUser($nick);
	return $this->users[$id];
	}
	
	private function addUser($nick) {
		$User = new IRC_User($nick, $this);
		$this->users[$User->id] = $User;
	}
	
	private function getChannel($channel) {
		$id = strtolower($channel);
		if(!isset($this->channels[$id])) $this->addChannel($channel);
	return $this->channels[$id];
	}
	
	private function addChannel($channel) {
		$Channel = new IRC_Channel($channel, $this);
		$this->channels[$Channel->id] = $Channel;
	return $Channel;
	}
	
	private function parseIRCMessage($string) {
		$parsed = array();
		if(!preg_match('/^(:(.+?) +?)?([A-Za-z]+?|[0-9]{3})( +?.+?)$/',$string,$tmp)) return false;
		$prefix  = $tmp[2];
		$command = $tmp[3];
		$params  = $tmp[4];
		
		if(!empty($prefix)) {
			preg_match('/^(.*?)(!(.*?))?(@(.*?))?$/',$prefix,$tmp);
			if(strstr($tmp[1],'.')) {
				$parsed['servername'] = $tmp[1];
			} else {
				$parsed['banmask'] = $tmp[0];
				$parsed['nick'] = $tmp[1];
				if(!empty($tmp[3])) $parsed['user'] = $tmp[3];
				if(!empty($tmp[5])) $parsed['host'] = $tmp[5];
			}
		}
		
		$parsed['command'] = $command;
		
		$params_array = array();
		do {
			preg_match('/^ ((:(.*?$))|((.*?)( .*)?))?$/',$params,$tmp);
			if(!empty($tmp[3])) {
				$trailing = $tmp[3];
				$params = "";
				$params_array[] = $trailing;
			} else {
				// TODO: Something's wrong here - Few strange messages get skipped
				if(empty($tmp[5])) break;
				$middle = $tmp[5];
				$params_array[] = $middle;
				
				// TODO: Something's wrong here - Few strange messages get skipped
				if(empty($tmp[6])) break;
				$params = $tmp[6];
			}
		} while(!empty($params));
		
		$parsed['params'] = $params_array;
	
	return $parsed;
	}
	
	public function tick() {
		if($this->floodCooldown !== false) $this->checkFloodCooldown();
		if($this->reconnect && ($this->floodCooldown === false || microtime(true) + 10 > $this->floodCooldown)) $this->reconnect();
		$this->doRecvq();
		$this->sendQueue();
		
		$raw = $this->read();
		
		if($raw !== false) {
			$this->queueRead[] = $this->getData($raw);
		} else {
			$idle_time = microtime(true) - $this->lastLifeSign;
			if($idle_time > 320) {
				$this->reconnect = true;
				$this->waitingForPong = false;
			} elseif(!$this->waitingForPong && $idle_time > 300) {
				$this->sendPing('are_you_still_there?_:)');
				$this->waitingForPong = true;
			}
		}
		
		return $this->readQueue();
	}
	
	private function checkFloodCooldown() {
		if(microtime(true) > $this->floodCooldown) $this->floodCooldown = false;
	}
	
	public function enqueueRead($data) {
		$this->queueRead[] = $data;
	}
	
	private function readQueue() {
		if(sizeof($this->queueRead) > 0) {
			return array_shift($this->queueRead);
		}
	
	return false;
	}
	
	public function sendQueue() {
		if($this->floodCooldown !== false) return false;
		
		$flood = $this->getVar('estimated_CLIENT_FLOOD');
		
		if(!empty($this->queueSend) && ($flood == -1 || $this->getRecvqSum()+strlen($this->queueSend[0]) < $flood)) {
			$this->write(array_shift($this->queueSend));
			return true;
		}
	return false;
	}
	
	private function doRecvq() {
		$time = microtime(true);
		
		if(empty($this->estimatedRecvq)) $this->lastRecvqRemoved = $time;
		elseif($time > $this->lastRecvqRemoved + $this->getVar('estimated_RECVQ_SPEED')) {
			array_shift($this->estimatedRecvq);
			$this->lastRecvqRemoved = $time;
		}
	}
	
	private function getRecvqSum() {
		$sum = 0;
		foreach($this->estimatedRecvq as $item) {
			$sum+= $item['length'];
		}
	
	return $sum;
	}
	
	private function onFlood() {
		$this->floodCooldown = microtime(true)+20;
		
		$this->queueSend = array();
		
		$new_client_flood = $this->getRecvqSum()-1;
		$new_recvq_speed = false;
		if($new_client_flood < 512) {
			// wtf network, not even 1 full msg / second - well, let's try to decrease our recvq-cleaning speed now
			$new_client_flood = -1;
			$new_recvq_speed = $this->getVar('estimated_RECVQ_SPEED')+1;
		}
		
		$text = sprintf('Ops - Looks like I flooded! Some messages may have gotten lost. Setting estimated CLIENT_FLOOD for %s from %s to %s.',
			$this->host,
			$this->getVar('estimated_CLIENT_FLOOD') == -1 ? 'unlimited' : $this->getVar('estimated_CLIENT_FLOOD'),
			$new_client_flood == -1 ? 'unlimited' : $new_client_flood
		);
		
		if($new_recvq_speed !== false) {
			$text.= sprintf(' Setting RECVQ_SPEED from %d to %d.',
				$this->getVar('estimated_RECVQ_SPEED'),
				$new_recvq_speed
			);
		}
		
		$sent = array();
		foreach($this->estimatedRecvq as $item) {
			$parsed = $this->parseIRCMessage(':'.$this->Me->banmask.' '.$item['msg']);
			$target = strtolower($parsed['params'][0]);
			
			if(array_search($target, $sent) === false) {
				$Target = false;
				if(isset($this->channels[$target])) $Target = $this->channels[$target];
				elseif(isset($this->users[$target])) $Target = $this->users[$target];
				
				if($Target !== false) {
					$Target->privmsg($text);
					$sent[] = $target;
				}
			}
		}
		
		$this->saveVar('estimated_CLIENT_FLOOD', $new_client_flood);
		if($new_recvq_speed !== false) $this->saveVar('estimated_RECVQ_SPEED', $new_recvq_speed);
	}

	public function getData($irc_msg) {
		$parsed = $this->parseIRCMessage($irc_msg);
		if(!$parsed) return false;
		
		$data['command'] = $parsed['command'];
		$data['raw']     = $irc_msg;
		
		switch($data['command']) {
			case '001':
				// First message sent by an IRC server after successful auth
				$data['server']          = $parsed['servername'];
				$data['my_nick']         = $parsed['params'][0];
				$data['welcome_message'] = $parsed['params'][1];
				
				$this->Me       = new IRC_User($data['my_nick'], $this);
				$this->users[$this->Me->id] = $this->Me;
				$this->NickServ = new IRC_User('NickServ', $this);
				$this->users[$this->NickServ->id] = $this->NickServ;
				$this->sendWhois($this->Me->nick);
				
				if(false !== $var = $this->getVar('nickserv_identify_command')) {
					$this->nickservIdentifyCommand = $var;
				} else {
					$this->NickServ->privmsg('ACC '.$this->Me->nick);
					$this->NickServ->privmsg('STATUS '.$this->Me->nick);
				}
			break;
			case '311':
				// WHOIS reply
				$User = $this->getUser($parsed['params'][1]);
				$User->user     = $parsed['params'][2];
				$User->host     = $parsed['params'][3];
				$User->realname = $parsed['params'][5];
				$User->banmask  = $User->nick.'!'.$User->user.'@'.$User->host;
				$data['User'] = $User;
			break;
			case '315':
				// End of WHO list (Channel join complete)
				$data['Channel'] = $this->getChannel($parsed['params'][1]);
			break;
			case '352':
				// Server WHO reply
				$Channel  = $this->getChannel($parsed['params'][1]);
				
				$User = $this->getUser($parsed['params'][5]);
				$User->user     = $parsed['params'][2];
				$User->host     = $parsed['params'][3];
				$User->realname = substr($parsed['params'][7], 2);
				$User->banmask  = $User->nick.'!'.$User->user.'@'.$User->host;
				
				if(!isset($Channel->users[$User->id])) {
					$Channel->addUser($User);
				}
				
				$User->modes[$Channel->id] = strlen($parsed['params'][6]) > 1 ? $parsed['params'][6]{1} : '';
			break;
			case '353':
				// Server NAMES reply
				$Channel = $this->getChannel($parsed['params'][2]);
				
				$users = explode(' ', $parsed['params'][3]);
				foreach($users as $user) {
					preg_match('/^([+@%])?(.+)$/', $user, $arr);
					$mode = $arr[1];
					$nick = $arr[2];
					
					$User = $this->getUser($nick);
					$User->modes[$Channel->id] = $mode;
				}
			break;
			case '366':
				// Server End of NAMES list
				$data['Channel'] = $this->getChannel($parsed['params'][1]);
			break;
			case '433':
				// Sent on connect if nickname is already in use
				$data['nick'] = $parsed['params'][1];
			break;
			case 'ERROR':
				// Sent when the server decides to close our connection
				$data['text'] = $parsed['params'][0];
				
				if(libString::endsWith('(Excess Flood)', $data['text'])) {
					$this->onFlood();
				}
				
				$this->floodCooldown = microtime(true) + 60;
				$this->reconnect = true;
			break;
			case 'JOIN':
				// Sent when the bot or a user joins a channel
				$User = $this->getUser($parsed['nick']);
				$User->banmask = $parsed['banmask'];
				$User->user    = $parsed['user'];
				$User->host    = $parsed['host'];
				$User->mode    = '';
				
				$Channel = $this->getChannel($parsed['params'][0]);
				$Channel->addUser($User);
				
				if($User->id != $this->Me->id) {
					$data['User']    = $User;
					$data['Channel'] = $Channel;
				}
			break;
			case 'KICK':
				// Sent when a user gets kicked from a channel
				$User         = $this->getUser($parsed['nick']);
				$Channel      = $this->getChannel($parsed['params'][0]);
				$Victim       = $this->getUser($parsed['params'][1]);
				$User->mode   = $User->modes[$Channel->id];
				$Victim->mode = $Victim->modes[$Channel->id];
				$kick_message = $parsed['params'][2];
				
				if($Victim->id == $this->Me->id) {
					$Channel->remove();
				} else {
					$data['Victim'] = $Victim;
					$Channel->removeUser($Victim);
				}
				
				$data['User']         = $User;
				$data['Channel']      = $Channel;
				$data['kick_message'] = $kick_message;
			break;
			case 'MODE':
				if(sizeof($parsed['params']) >= 3) {
					// Sent if a mode for a user in a channel is changed
					// TODO: Many modes with 1 command
					$User    = $this->getUser($parsed['nick']);
					$Victim  = $this->getUser($parsed['params'][2]);
					$Channel = $this->getChannel($parsed['params'][0]);
					$Channel->sendNames();
					// TODO: onMode() Event
				} else {
					if(isset($parsed['user'])) {
						// TODO: Sent when the channel modes are changed
					} else {
						// TODO: Sent on connect to show us our user modes on the server
					}
				}
			break;
			case 'NICK':
				// Sent when a user or the bot changes nick
				$User = $this->getUser($parsed['nick']);
				if($User->id != $this->Me->id) {
					$data['User'] = $User;
				}
				
				$data['old_nick'] = $User->nick;
				$User->changeNick($parsed['params'][0]);
			break;
			case 'NOTICE':
				if(isset($parsed['nick'])) {
					// Sent when a user sends a notice
					$User = $this->getUser($parsed['nick']);
					$text = $parsed['params'][1];
				
					if($User->id == 'nickserv') {
						// Sent when nickserv sends a notice
						$tmp = explode(' ', $parsed['params'][1]);
						if($tmp[0] == $this->Me->nick && $tmp[1] == 'ACC') {
							$this->nickservIdentifyCommand = 'ACC';
							$this->saveVar('nickserv_identify_command', 'ACC');
						} elseif($tmp[0] == 'STATUS' && $tmp[1] == $this->Me->nick) {
							$this->nickservIdentifyCommand = 'STATUS';
							$this->saveVar('nickserv_identify_command', 'STATUS');
						}
						
						$id = false;
						if($tmp[1] == 'ACC')        $id = strtolower($tmp[0]);
						elseif($tmp[0] == 'STATUS') $id = strtolower($tmp[1]);
						if($id && isset($this->users[$id])) $this->users[$id]->nickservStatus = $tmp[2];
					}
					
					$data['User'] = $User;
					$data['text'] = $text;
				} else {
					if(isset($parsed['servername'])) $data['servername'] = $parsed['servername'];
					else $parsed['servername'] = false;
					// $data['target'] = $parsed['params'][0];
					$data['message'] = $parsed['params'][1];
					
					if($this->floodCooldown === false && preg_match('/Message to .+? throttled/', $data['message'])) {
						$this->onFlood();
					}
				}
			break;
			case 'PART':
				// Sent when a user or the bot parts a channel
				$User       = $this->getUser($parsed['nick']);
				$Channel    = $this->getChannel($parsed['params'][0]);
				$User->mode = $User->modes[$Channel->id];
				unset($User->modes[$Channel->id]);
				
				if($User->id == $this->Me->id) {
					$Channel->remove();
				} else {
					$Channel->removeUser($User);
					$data['User'] = $User;
				}
				
				$data['Channel'] = $Channel;
				
				if(isset($parsed['params'][1])) {
					$data['part_message'] = $parsed['params'][1];
				}
			break;
			case 'PING':
				// Ping message sent from the server to see if we're still alive
				$data['challenge'] = $parsed['params'][0];
				$this->sendPong($data['challenge']);
			break;
			case 'PONG':
				// Message sent by the server after we issued a PING
				$data['server']    = $parsed['params'][0];
				$data['challenge'] = $parsed['params'][1];
				$this->waitingForPong = false;
			break;
			case 'PRIVMSG':
				// Sent when a user sends a message to a channel where the bot is in, or to the bot itself
				if(!isset($this->users[strtolower($parsed['nick'])])) $this->sendWhois($parsed['nick']);
				$data['User'] = $this->getUser($parsed['nick']);;
				
				// TODO: fail with todo at parseIRCMessage
				$data['text'] = isset($parsed['params'][1]) ? $parsed['params'][1] : '';
				
				if(strtolower($parsed['params'][0]) == $this->Me->id) {
					$data['isQuery'] = true;
					$data['User']->mode = '';
				} else {
					$data['isQuery']    = false;
					$data['Channel']    = $this->getChannel($parsed['params'][0]);
					$data['User']->mode = $data['User']->modes[$data['Channel']->id];
				}
			break;
			case 'QUIT':
				// Sent when a user quits the server
				$User = $this->getUser($parsed['nick']);
				$User->remove();
				
				$data['User'] = $User;
				
				// TODO: fail with todo at parseIRCMessage
				if(isset($parsed['params'][0])) $data['quit_message'] = $parsed['params'][0];
				else $data['quit_message'] = '';
			break;
		}
		
		$this->lastLifeSign = microtime(true);
		
	return $data;
	}
	
	public function sendRaw($string, $bypass_queue=false) {
		if($bypass_queue) $this->write($string);
		else $this->queueSend[] = $string;
	}
	
	public function sendPing($challenge=null) {
		$this->sendRaw('PING '.(isset($challenge) ? $challenge : $this->host), true);
	}
	
	public function sendPong($challenge) {
		$this->sendRaw('PONG :'.$challenge, true);
	}
	
	public function sendWhois($nick) {
		$this->sendRaw('WHOIS '.$nick, true);
	}
	
	public function setPass($pass) {
		$this->myData['pass'] = $pass;
		
		$this->sendRaw('PASS '.$pass, true);
	}
	
	public function setUser($username, $hostname, $servername, $realname) {
		$this->myData['username']   = $username;
		$this->myData['hostname']   = $hostname;
		$this->myData['servername'] = $servername;
		$this->myData['realname']   = $realname;
		
		$this->sendRaw('USER '.$username.' '.$hostname.' '.$servername.' :'.$realname, true);
	}
	
	public function setNick($nick) {
		$this->myData['nick'] = $nick;
		
		$this->sendRaw('NICK '.$nick, true);
	}
	
	public function joinChannel($channel, $key=false) {
		$this->sendRaw('JOIN '.$channel.($key?' '.$key:''), true);
	}
	
	public function quit($message=null) {
		if(isset($message)) $this->sendRaw('QUIT :'.$message, true);
		else                $this->sendRaw('QUIT', true);
	}
	
	public function saveVar($name, $value) {
		$this->Bot->savePermanent($name, $value, 'server', $this->id);
	}
	
	public function getVar($name) {
		return $this->Bot->getPermanent($name, 'server', $this->id);
	}
	
	public function removeVar($name) {
		$this->Bot->removePermanent($name, 'server', $this->id);
	}
	
	function __destruct() {
		$this->quit('Terminating');
	}
	
}

?>
