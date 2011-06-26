<?php

require_once('defaults.php');

require_once('classes/MySQL.php');
require_once('classes/IRC_Server.php');
require_once('classes/Plugin.php');


class Nimda {
	
	public $servers = array();
	public $plugins = array();
	
	private $CONFIG = array();
	private $MySQL;
	
	function __construct() {
		$this->initBot();
		
		while(true) {
			if(empty($this->servers)) {
				echo 'No servers to read from - Qutting'."\n";
				break;
			}
			
			$check = false;
			foreach($this->servers as $Server) {
				//echo $Server->host."\n";
				if(false === $data = $Server->getData()) continue;
				$check = true;
				unset($data['raw']); // TODO: Logging & stuff
				$this->triggerPlugins($data, $Server);
			}
			if(!$check) usleep(20000);
		}
	}
	
	private function initBot() {
		$this->CONFIG = libFile::parseConfigFile('nimda.conf');
		$this->MySQL = new MySQL(
			$this->CONFIG['mysql_host'],
			$this->CONFIG['mysql_user'],
			$this->CONFIG['mysql_pass'],
			$this->CONFIG['mysql_db']
		);
		
		$this->initServers();
		$this->initPlugins();
	}
	
	private function initServers() {
		$servers = $this->MySQL->query('SELECT * FROM servers WHERE active=1');
		if(!$servers['count']) die('Error: No servers defined (check mysql table `servers`)'."\n");
		foreach($servers['result'] as $data) {
			$this->connectServer($data);
		}
	}
	
	public function connectServer($data) {
		// Expects 1 full row of mysql table `servers`
		$Server = new IRC_Server($data['host'], $data['port'], $data['ssl']);
		if(!empty($data['password'])) $Server->setPass($data['password']);
		$Server->setUser(
			$data['my_username'],
			$data['my_hostname'],
			$data['my_servername'],
			$data['my_realname']
		);
		$Server->setNick($data['my_username']);
		$Server->id = $data['id'];
		
		$this->servers[$data['id']] = $Server;
	}
	
	private function initPlugins() {
		$userplugin_files = libFilesystem::getFiles('plugins/user/', 'php');
		$coreplugin_files = libFilesystem::getFiles('plugins/core/', 'php');
		
		$files = array();
		foreach($coreplugin_files as $file) {
			$files[] = array('filename' => $file, 'isCore' => true);
		}
		foreach($userplugin_files as $file) {
			$files[] = array('filename' => $file, 'isCore' => false);
		}
		
		foreach($files as $file) {
			$classname = substr($file['filename'], 0, -4);
			list($crap, $plugin_name) = explode('_', $classname, 2);
			echo 'Loading '.($file['isCore']?'core':'user').' plugin '.$plugin_name.'..'."\n";
			
			require_once('plugins/'.($file['isCore']?'core/':'user/').$file['filename']);
			$Plugin = new $classname($this, $this->MySQL);
			$Plugin->onLoad();
			$this->plugins[] = $Plugin;
		}
	}
	
	private function triggerPlugins($data, $Server) {
	
		$command = $data['command'];
		unset($data['command']);
		
		if(isset($data['User'])) {
			$User = $data['User'];
			unset($data['User']);
		} else {
			$User = false;
		}
		
		if(isset($data['Channel'])) {
			$Channel = $data['Channel'];
			unset($data['Channel']);
		} else {
			$Channel = false;
		}
		
	
		foreach($this->plugins as $Plugin) {
			$Plugin->Server  = $Server;
			$Plugin->Channel = $Channel;
			$Plugin->User    = $User;
			$Plugin->data    = $data;
			$Plugin->command = $command;
			
			switch($command) {
				case '001':     $Plugin->onConnect(); break;
				case '311':     /* TODO: WHOIS reply */ break;
				case '315':     $Plugin->onMeJoin();  break;
				case '433':     $Plugin->onNickAlreadyInUse(); break;
				case 'ERROR':   $Plugin->onMeQuit();  break;
				case 'JOIN':    if($User) $Plugin->onJoin(); break;
				case 'KICK':    if(isset($data['Victim'])) $Plugin->onKick(); else $Plugin->onMeKick(); break;
				case 'NICK':    if($User) $Plugin->onNick(); else $Plugin->onMeNick(); break;
				case 'PART':    if($User) $Plugin->onPart(); else $Plugin->onMePart(); break;
				case 'PING':    $Plugin->onPing();    break;
				case 'PRIVMSG': $Plugin->onPrivmsg(); break;
				case 'QUIT':    $Plugin->onQuit(); break;
			}
		}
	}
	
}

?>
