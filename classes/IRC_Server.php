<?php

require_once('IRC_Channel.php');
require_once('IRC_User.php');

final class IRC_Server {
	
	private $socket = false;
	private $sendQueue = array();
	private $estimatedRecvq = array('messages' => array(), 'size' => 0, 'last_message_removed' => 0);
	
	private $waitingForPong = false;
	private $floodCooldown = false;
	private $connectCooldown = array('cooldown' => 0, 'last_connect' => 0);
	private $lastLifeSign = 0;
	private $myData = array();
	private $supports = array();
	
	public $Bot;
	public $id;
	public $channels  = array();
	public $users     = array();
	public $host         = '';
	public $port         = 0;
	public $isSSL        = false;
	public $useSASL      = false;
	public $bind         = '';
	public $Me;
	//public $NickServ;
	//public $nickservIdentifyCommand = false;
	
	public function __construct($Bot, $name, $host, $port, $ssl=false, $bind='0.0.0.0:0', $useSASL = false) {
		$this->Bot          = $Bot;
		$this->id           = $name;
		$this->host         = $host;
		$this->port         = $port;
		$this->isSSL        = $ssl;
		$this->bind         = $bind;
		$this->useSASL      = $useSASL;
		
		if($this->getVar('estimated_CLIENT_FLOOD') === false) $this->saveVar('estimated_CLIENT_FLOOD', -1);
		if($this->getVar('estimated_RECVQ_SPEED') === false)  $this->saveVar('estimated_RECVQ_SPEED', 1);
	}
	
	public function tick() {
		if(!$this->socket && !$this->connect()) return false;
		
		$this->doEstimatedRecvq();
		$this->doSendQueue();
		
	return $this->readMessage();
	}
	
	private function connect() {
		$c = &$this->connectCooldown;
		if($c['last_connect'] + $c['cooldown'] > time()) return false;
		
		$this->socket = @stream_socket_client(
			($this->isSSL?'ssl://':'').$this->host.':'.$this->port,
			$errno,
			$errstr,
			5,
			STREAM_CLIENT_CONNECT,
			stream_context_create(array('socket' => array('bindto' => $this->bind)))
		);
		
		if(!$this->socket) {
			if(!$c['cooldown']) $c['cooldown'] = 1;
			elseif($c['cooldown'] < 300) $c['cooldown']*= 2;
			$c['last_connect'] = time();
			
			echo 'Could not connect to '.$this->host.' on '.$this->port.' because "'.$errstr.'". Trying again in '.$c['cooldown']." seconds\n";
			return false;
		}
		
		stream_set_blocking($this->socket, 0);
		
		$d = &$this->myData;
		
		if ($this->useSASL) {
			$this->sendRaw('CAP REQ :sasl', true);
		} else {
			if(isset($d['pass']) && !empty($d['pass'])) {
				$this->sendRaw('PASS '.$d['pass'], true);
			}
		}
		
		$this->sendRaw('USER '.$d['username'].' '.$d['hostname'].' '.$d['servername'].' :'.$d['realname'], true);
		$this->sendRaw('NICK '.$d['nick'], true);
		
		if ($this->useSASL) {
			$this->waitFor(function($msg) {
				$parts = explode(' ', $msg['raw']);
				return count($parts) > 3 && $parts[1] === 'CAP' && $parts[3] === 'ACK';
			});
			
			$this->sendRaw('AUTHENTICATE PLAIN', true);
			
			$this->waitFor(function($msg) {
				return strpos($msg['raw'], 'AUTHENTICATE +') !== false;
			});
			
			$auth = base64_encode(implode(chr(0), [$d['username'], $d['username'], $d['pass']]));
			$this->sendRaw('AUTHENTICATE ' . $auth, true);
			$this->sendRaw('AUTHENTICATE +', true);
			$this->sendRaw('CAP END', true);
		}
		
		$this->lastLifeSign   = microtime(true);
		$this->estimatedRecvq = array('messages' => array(), 'size' => 0, 'last_message_removed' => 0);
		$this->connectCooldown = array('cooldown' => 0, 'last_connect' => time());
	
	return true;
	}
	
	private function waitFor($callback) {
		do {
			$msg = $this->readMessage();
		} while ($msg === false || !$callback($msg));
		
		return $msg;
	}
	
	private function read() {
		if(!$this->socket) return false;
		
		$message = fgets($this->socket);
		if(!$message) return false;
		while($message{strlen($message)-1} != "\n") $message.= fgets($this->socket);
		
		$message = trim($message);
		echo '>> '.$message."\n";
		
		$this->lastLifeSign = microtime(true);
		
	return $message;
	}

	private function write($string) {
		if(!$this->socket) return false;
		
		echo '<< '.$string."\n";
		fputs($this->socket, $string."\r\n");
		if($this->getVar('estimated_CLIENT_FLOOD') != -1) {
			$this->estimatedRecvq['messages'][] = $string;
			$this->estimatedRecvq['size']+= strlen($string);
		}
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
	
	private function readMessage() {
		$raw = $this->read();
		
		if($raw !== false) {
			return $this->getData($raw);
		} else {
			$idle_time = microtime(true) - $this->lastLifeSign;
			if($idle_time > 320) {
				$this->quit('Connection lost - Reconnecting');
				$this->waitingForPong = false;
			} elseif(!$this->waitingForPong && $idle_time > 300) {
				$this->sendPing('are_you_still_there?_:)');
				$this->waitingForPong = true;
			}
			
			return false;
		}
	}
	
	private function getData($irc_msg) {
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
				$this->sendWhois($this->Me->nick);
				
				/*
				$this->NickServ = new IRC_User('NickServ', $this);
				$this->users[$this->NickServ->id] = $this->NickServ;
				
				
				if(false !== $var = $this->getVar('nickserv_identify_command')) {
					$this->nickservIdentifyCommand = $var;
				} else {
					$this->NickServ->privmsg('ACC '.$this->Me->nick);
					$this->NickServ->privmsg('STATUS '.$this->Me->nick);
				}
				*/
			break;
			case '005':
				// ISUPPORT
				for($i=1;$i<sizeof($parsed['params'])-1;$i++) {
					$tmp = explode('=', $parsed['params'][$i]);
					$name = strtoupper($tmp[0]);
					
					if(sizeof($tmp) == 2) {
						$this->supports[$name] = $tmp[1];
					} else {
						$this->supports[$name] = true;
					}
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
			case '324':
				// Server MODE reply
				$Channel = $this->getChannel($parsed['params'][1]);
				$modes = $parsed['params'][2];
				$Channel->modes = array();
				for($i=1;$i<strlen($modes);$i++) {
					$Channel->modes[$modes{$i}] = true;
				}
			break;
			case '332':
				// Server "get" TOPIC reply (Also on channel join)
				$Channel = $this->getChannel($parsed['params'][1]);
				$Channel->topic = $parsed['params'][2];
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
				
				$modes = $parsed['params'][6];
				$mode = '';
				// Get the most significant mode, ignoring * (IRC OP) and the first 'H' char which isn't a mode
				for($i=1;$i<strlen($modes);$i++) {
					if($modes{$i} != '*') {
						$mode = $modes{$i};
						break;
					}
				}
				
				$User->modes[$Channel->id] = $mode;
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
					$this->connectCooldown['cooldown'] = 20;
					$this->connectCooldown['last_connect'] = time();
				} elseif(libString::endsWith('throttled', $data['text'])) {
					$this->connectCooldown['cooldown']     = 60;
					$this->connectCooldown['last_connect'] = time();
				}
				
				fclose($this->socket);
				$this->socket = false;
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
				// TODO: Fix this up - It's not really correct
				if(sizeof($parsed['params']) >= 3) {
					// Sent if a mode for a user in a channel is changed
					// TODO: Many modes with 1 command
					$User    = $this->getUser($parsed['nick']);
					$Victim  = $this->getUser($parsed['params'][2]);
					$Channel = $this->getChannel($parsed['params'][0]);
					$Channel->sendNames();
					
					$data['User']    = $User;
					$data['Channel'] = $Channel;
					$data['Victim']  = $Victim;
					$data['mode']    = $parsed['params'][1];
				} else {
					if(isset($this->channels[$parsed['params'][0]])) {
						// Sent when the channel modes are changed
						$Channel = $this->getChannel($parsed['params'][0]);
						$modes = $parsed['params'][1];
						$action = '';
						for($i=0;$i<strlen($modes);$i++) {
							if($modes{$i} == '+') $action = 'add';
							elseif($modes{$i} == '-') $action = 'rm';
							else {
								switch($action) {
									case 'add':
										$Channel->modes[$modes{$i}] = true;
									break;
									case 'rm':
										unset($Channel->modes[$modes{$i}]);
									break;
								}
							}
						}
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
					
					/*
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
					*/
					
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
				
				if($data['text']{0} == "\x01") {
					$data['text'] = substr($data['text'], 1);
					if(substr($data['text'], -1) == "\x01") {
						$data['text'] = substr($data['text'], 0, -1);
					}
					
					$data['command'] = 'vCTCP';
					$tmp = explode(' ', $data['text'], 2);
					$data['ctcp_command'] = strtoupper($tmp[0]);
					if(isset($tmp[1])) {
						$data['text'] = $tmp[1];
					} else {
						$data['text'] = '';
					}
					
					if($data['ctcp_command'] == 'ACTION') {
						unset($data['ctcp_command']);
						$data['command'] = 'vACTION';
					}
				}
			break;
			case 'TOPIC':
				// Sent when a user changes the topic
				$User = $this->getUser($parsed['nick']);
				$Channel = $this->getChannel($parsed['params'][0]);
				$Channel->topic = $parsed['params'][1];
				
				$data['User'] = $User;
				$data['Channel'] = $Channel;
				$data['topic'] = $parsed['params'][1];
				
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
		
	return $data;
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
	
	public function doSendQueue() {
		if(empty($this->sendQueue)) return false;
		
		if($this->floodCooldown !== false) {
			if(microtime(true) > $this->floodCooldown) $this->floodCooldown = false;
			else return false;
		}
		
		if($this->getVar('estimated_CLIENT_FLOOD') !== -1 &&
			($this->estimatedRecvq['size'] + strlen($this->sendQueue[0]) > $this->getVar('estimated_CLIENT_FLOOD')) ||
			(sizeof($this->estimatedRecvq['messages']) > 5)
		) return false;
		
		
		$this->write(array_shift($this->sendQueue));
		
	return true;
	}
	
	private function doEstimatedRecvq() {
		if($this->getVar('estimated_CLIENT_FLOOD') == -1) return;
		
		$time = microtime(true);
		$recvq = &$this->estimatedRecvq;
		
		if($recvq['size'] == 0) {
			$recvq['last_message_removed'] = $time;
			return;
		}
		
		if($time > $recvq['last_message_removed'] + $this->getVar('estimated_RECVQ_SPEED')) {
			$msg = array_shift($recvq['messages']);
			$recvq['size']-= strlen($msg);
			$recvq['last_message_removed'] = $time;
		}
	}
	
	private function onFlood() {
		$this->floodCooldown = microtime(true)+40;
		
		$this->sendQueue = array();
		
		$new_client_flood = $this->estimatedRecvq['size'] ? $this->estimatedRecvq['size']-1 : 10000;
		$new_recvq_speed = false;
		if($new_client_flood < 512) {
			// wtf network, not even 1 full msg / second - well, let's try to decrease our recvq-cleaning speed now
			$new_client_flood = 1024;
			$new_recvq_speed = $this->getVar('estimated_RECVQ_SPEED')+1;
		}
		
		$text = sprintf("\x02Ops - Looks like I flooded!\x02 Some messages may have gotten lost. Setting estimated CLIENT_FLOOD for %s from %s to %s.",
			$this->host,
			$this->getVar('estimated_CLIENT_FLOOD') == -1 ? 'unlimited' : $this->getVar('estimated_CLIENT_FLOOD'),
			$new_client_flood == -1 ? 'unlimited' : $new_client_flood
		);
		
		if($new_recvq_speed !== false) {
			$text.= sprintf(' Setting RECVQ_SPEED from %d to %d.',
				$this->getVar('estimated_RECVQ_SPEED'),
				$new_recvq_speed
			);
		} else {
			$text.= sprintf(' Estimated RECVQ_SPEED is %d.',
				$this->getVar('estimated_RECVQ_SPEED')
			);
		}
		
		$sent = array();
		foreach($this->estimatedRecvq['messages'] as $msg) {
			$parsed = $this->parseIRCMessage(':'.$this->Me->banmask.' '.$msg);
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
	
	public function sendRaw($string, $bypass_queue=false) {
		if($bypass_queue) $this->write($string);
		else $this->sendQueue[] = $string;
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
	}
	
	public function setUser($username, $hostname, $servername, $realname) {
		$this->myData['username']   = $username;
		$this->myData['hostname']   = $hostname;
		$this->myData['servername'] = $servername;
		$this->myData['realname']   = $realname;
	}
	
	public function setNick($nick) {
		$this->myData['nick'] = $nick;
		
		if($this->socket) $this->sendRaw('NICK '.$nick, true);
	}
	
	public function joinChannel($channel, $key=false) {
		$this->sendRaw('JOIN '.$channel.($key?' '.$key:''), true);
	}
	
	public function quit($message=null) {
		if(isset($message)) $this->sendRaw('QUIT :'.$message, true);
		else                $this->sendRaw('QUIT', true);
		
		if($this->socket) {
			fclose($this->socket);
			$this->socket = false;
		}
	}
	
	public function getSupport($name) {
		$name = strtoupper($name);
		
		switch($name) {
			case 'MODES':
				if(!isset($this->supports[$name])) return 1;
			break;
		}
		
		if(!isset($this->supports[$name])) return false;
		
	return $this->supports[$name];
	}
	
	public function saveVar($name, $value) {
		$this->Bot->savePermanent($name, $value, 'server', $this->id);
	}
	
	public function getVar($name, $default=false) {
		$value = $this->Bot->getPermanent($name, 'server', $this->id);
		if($value === false) return $default;
		
	return $value;
	}
	
	public function removeVar($name) {
		$this->Bot->removePermanent($name, 'server', $this->id);
	}
	
	function __destruct() {
		$this->quit('Terminating');
	}
	
}

?>
