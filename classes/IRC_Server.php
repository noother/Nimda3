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
	public $serverID     = 0;
	public $myNick       = '';
	public $myID;
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
	
	private function addUser($banmask) {
		$data = $this->parseBanmask($banmask);
		//while(strlen($data['host']) < 1000000) $data['host'].='AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
		
		$User = new IRC_User(
			$data['nick'],
			$data['user'],
			$data['host'],
			$data['banmask'],
			$this
		);
		
		$this->users[$User->id] = $User;
	
	return $User;
	}
	
	private function removeUser($User) {
		foreach($User->channels as $Channel) {
			unset($Channel->users[$User->id]);
		}
		unset($this->users[$User->id]);
	}
	
	private function addChannel($channel) {
		$Channel = new IRC_Channel($channel, $this);
		$this->channels[$Channel->id] = $Channel;
	return $Channel;
	}
	
	private function removeChannel($Channel) {
		foreach($Channel->users as $User) {
			$this->removeUserFromChannel($User, $Channel);
		}
		unset($this->channels[$Channel->id]);
	}
	
	private function removeUserFromChannel($User, $Channel) {
		if(sizeof($User->channels) == 1) {
			$this->removeUser($User);
		} else {
			unset($Channel->users[$User->id]);
			unset($User->channels[$Channel->id]);
		}
	}
	
	private function changeUserName($User, $new_name) {
		$new_id = strtolower($new_name);
		
		foreach($User->channels as $Channel) {
			unset($Channel->users[$User->id]);
			$Channel->users[$new_id] = $User;
		}
		
		unset($this->users[$User->id]);
		$this->users[$new_id] = $User;
		
		$User->name = $new_name;
	}
	
	private function parseBanmask($banmask) {
		preg_match('/^(.+?)!~?(.+?)@(.+?)$/', $banmask, $arr);
		$data = array(
			'banmask' => $arr[0],
			'nick' => $arr[1],
			'user' => $arr[2],
			'host' => $arr[3]
		);
		
	return $data;
	}
	
	private function getUserByBanmask($banmask) {
		$data = $this->parseBanmask($banmask);
		$id = strtolower($data['nick']);
		if(isset($this->users[$id])) return $this->users[$id];
	return false;
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
				// First message sent by an IRC server after auth
				$data['server']          = $parsed['servername'];
				$data['my_nick']         = $parsed['params'][0];
				$data['welcome_message'] = $parsed['params'][1];
				
				$this->myNick = $data['my_nick'];
				$this->myID   = strtolower($data['my_nick']);
			break;
			case '315':
				// End of WHO list (Channel join complete)
				$data['Channel'] = $this->channels[strtolower($parsed['params'][1])];
			break;
			case '352':
				// Server WHO reply
				$Channel  = $this->channels[strtolower($parsed['params'][1])];
				$user     = $parsed['params'][2];
				$host     = $parsed['params'][3];
				$nick     = $parsed['params'][5];
				$realname = substr($parsed['params'][7], 2);
				$banmask = $nick.'!'.$user.'@'.$host;
				
				if(false === $User = $this->getUserByBanmask($banmask)) {
					$User = $this->addUser($banmask);
				}
				
				$User->realname = $realname;
				
				if(!isset($Channel->users[$User->id])) {
					$Channel->users[$User->id] = $User;
				}
				
				if(!isset($User->channels[$Channel->id])) {
					$User->channels[$Channel->id] = $Channel;
				}
			break;
			case 'ERROR':
				// Sent when the bot quitted the server
				foreach($this->channels as $Channel) {
					$this->removeChannel($Channel);
				}
			break;
			case 'JOIN':
				// Sent when the bot or a user joins a channel
				if(false === $User = $this->getUserByBanmask($parsed['banmask'])) {
					$User = $this->addUser($parsed['banmask']);
				}
				
				if($User->id == $this->myID) {
					$Channel = $this->addChannel($parsed['params'][0]);
				} else {
					$Channel = $this->channels[strtolower($parsed['params'][0])];
				}
				
				$Channel->users[$User->id]    = $User;
				$User->channels[$Channel->id] = $Channel;
				
				if($User->id != $this->myID) $data['User'] = $User;
				$data['Channel'] = $Channel;
			break;
			case 'KICK':
				// Sent when a user gets kicked from a channel
				$User         = $this->users[strtolower($parsed['nick'])];
				$Channel      = $this->channels[strtolower($parsed['params'][0])];
				$Victim       = $this->users[strtolower($parsed['params'][1])];
				$kick_message = $parsed['params'][2];
				
				if($Victim->id == $this->myID) {
					$this->removeChannel($Channel);
				} else {
					$data['Victim']  = $Victim;
					$this->removeUserFromChannel($Victim, $Channel);
				}
				
				$data['User']         = $User;
				$data['Channel']      = $Channel;
				$data['kick_message'] = $kick_message;
			break;
			case 'NICK':
				// Sent when a user or the bot changes nick
				$User = $this->users[strtolower($parsed['nick'])];
				$old_name = $User->name;
				$old_id   = $User->id;
				$new_name = $parsed['params'][0];
				$new_id   = strtolower($new_name);
				$this->changeUserName($User, $new_name);
				
				if($old_id == $this->myID) {
					$this->myNick = $new_name;
					$this->myID   = $new_id;
				} else {
					$data['User'] = $User;
				}
				
				$data['old_name'] = $old_name;
			break;
			case 'PART':
				// Sent when a user or the bot parts a channel
				$User    = $this->users[strtolower($parsed['nick'])];
				$Channel = $this->channels[strtolower($parsed['params'][0])];
				
				if($User->id == $this->myID) {
					$this->removeChannel($Channel);
				} else {
					$data['User']    = $User;
					$this->removeUserFromChannel($User, $Channel);
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
				if(false === $User = $this->getUserByBanmask($parsed['banmask'])) {
					$User = $this->addUser($parsed['banmask']);
				}
				
				$data['User'] = $User;
				$data['text'] = $parsed['params'][1];
				if(strtolower($parsed['params'][0]) == $this->myID) {
					$data['isQuery'] = true;
				} else {
					$data['isQuery'] = false;
					$data['Channel'] = $this->channels[strtolower($parsed['params'][0])];
				}
			break;
			case 'QUIT':
				// Sent when a user quits the server
				$User = $this->users[strtolower($parsed['nick'])];
				$this->removeUser($User);
				
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
	
	public function setPass($pass) {
		$this->sendRaw('PASS', $pass);
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
