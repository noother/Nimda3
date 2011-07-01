<?php

require_once('IRC_Channel.php');
require_once('IRC_User.php');



final class IRC_Server {
	
	private $socket;
	
	public $channels  = array();
	public $users     = array();
	public $host         = '';
	public $port         = 0;
	public $isSSL        = false;
	public $Me;
	public $lastLifeSign = 0;
	
	public function __construct($host, $port, $ssl=false) {
		$this->socket = fsockopen(($ssl?'ssl://':'').$host, $port);
		stream_set_blocking($this->socket, 0);
		
		$this->host         = $host;
		$this->port         = $port;
		$this->isSSL        = $ssl;
		$this->lastLifeSign = libSystem::getMicrotime();
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
		fputs($this->socket, $string."\n");
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

	public function getData() {
		if(false === $raw = $this->read()) return false;
		$parsed = $this->parseIRCMessage($raw);
		if(!$parsed) return false;
		
		$data['command'] = $parsed['command'];
		$data['raw']     = $raw;
		
		switch($data['command']) {
			case '001':
				// First message sent by an IRC server after successful auth
				$data['server']          = $parsed['servername'];
				$data['my_nick']         = $parsed['params'][0];
				$data['welcome_message'] = $parsed['params'][1];
				
				$this->Me = new IRC_User($data['my_nick'], $this);
				$this->sendWhois($this->Me->nick);
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
			case '433':
				// Sent on connect if nickname is already in use
				$data['nick'] = $parsed['params'][1];
			break;
			case 'ERROR':
				// Sent when the bot quitted the server
				foreach($this->channels as $Channel) {
					$Channel->remove();
				}
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
				if(sizeof($parsed['params']) == 3) {
					// Sent if a mode for a user in a channel is changed
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
				$User->changeNick($new_nick);
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
			case 'PRIVMSG':
				// Sent when a user sends a message to a channel where the bot is in, or to the bot itself
				$data['User'] = $this->getUser($parsed['nick']);
				
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
		
		$this->lastLifeSign = libSystem::getMicrotime();
		
		
	return $data;
	}
	
	public function sendRaw($string) {
		$this->write($string);
	}
	
	public function sendPong($string) {
		$this->sendRaw('PONG :'.$string);
	}
	
	public function sendWhois($nick) {
		$this->sendRaw('WHOIS '.$nick);
	}
	
	public function setPass($pass) {
		$this->sendRaw('PASS '.$pass);
	}
	
	public function setUser($username, $hostname, $servername, $realname) {
		$this->sendRaw('USER '.$username.' '.$hostname.' '.$servername.' :'.$realname);
	}
	
	public function setNick($nick) {
		$this->sendRaw('NICK '.$nick);
	}
	
	public function joinChannel($channel, $key=false) {
		$this->sendRaw('JOIN '.$channel.($key?' '.$key:''));
	}
	
	public function quit($message=null) {
		if(isset($message)) $this->sendRaw('QUIT :'.$message);
		else                $this->sendRaw('QUIT');
	}
	
	public function __destruct() {
		fclose($this->socket);
	}
	
}

?>
