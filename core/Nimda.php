<?php

require_once('defaults.php');

require_once('core/Plugin.php');

require_once('classes/IRC_Server.php');


class Nimda {
	
	public $servers = array();
	public $plugins = array();
	public $time;
	/** @var  DatabaseInterface */
	public $database;
	public $version;
	
	public $timerCount;
	public $jobCount;
	public $jobCountMax;
	
	private $CONFIG = array();
	private $timersLastTriggered;
	private $permanentVars = array();
	private $tempDir;
	private $jobsDoneDP;
	
	function __construct() {
		pcntl_signal(SIGINT,   array($this, 'cleanShutdown'));
		pcntl_signal(SIGTERM,  array($this, 'cleanShutdown'));
		
		$this->initBot();
		
		while(true) {
			if(empty($this->servers)) {
				echo 'No servers to read from - Qutting'."\n";
				break;
			}
			
			$this->time = time();
			$this->triggerTimers();
			$this->triggerJobs();
			
			$check = false;
			foreach($this->servers as $Server) {
				if(false !== $data = $Server->tick()) {
					$check = true;
					if(is_array($data)) {
						unset($data['raw']); // TODO: Logging & stuff
						$this->triggerPlugins($data, $Server);
						$Server->doSendQueue();
					}
				}
			}
			if(!$check) usleep(20000);
			
		}
	}
	
	private function initBot() {
		$this->CONFIG = libFile::parseConfigFile('nimda.conf');

        switch ($this->CONFIG['database']) {
            case 'mysql':
                $this->database = new MySQL([
                    'host' => $this->CONFIG['mysql_host'],
                    'user' => $this->CONFIG['mysql_user'],
                    'pass' => $this->CONFIG['mysql_pass'],
                    'db' => $this->CONFIG['mysql_db']
                ]);
                break;
            case 'sqlite':
                $this->database = new SQLite([
                    'file' => $this->CONFIG['sqlite_file'],
                    'readonly' => $this->CONFIG['sqlite_readonly'],
                    'create' => $this->CONFIG['sqlite_create'],
                    'encryptionKey' => $this->CONFIG['sqlite_encryptionKey'],
                ]);
                break;
            case 'mongodb':
                die('I\'m so sorry you were led astray from the path of light. Please return to the world of RDBMS');
                break;
            default:
                die("Unknown database backend type {$this->CONFIG['database']}");
                break;
        }

		$this->autoUpdateSQL();
		
		$this->createTempDir();
		$this->initJobs();
		$this->initPlugins();
		$this->initServers();
		$this->timersLastTriggered = time()+10; // Give him some time to do the connecting before triggering the timers
	}
	
	private function autoUpdateSQL() {
		echo "Checking for updates..\n";
		$tmp = $this->database->showTablesLike('version');
		if(count($tmp) == 0 || $tmp == false) $current_version = 0;
		else $current_version = $this->database->fetchColumn("SELECT `version` FROM `version`");
		if($current_version === false) die("Error: Table version exists but has no entry.\n");
		$this->version = '3.'.$current_version.'.x';

		$update_file = '';
		if($this->CONFIG['database'] == 'mysql')
			$update_file = 'core/sql_updates';
		else
			$update_file = 'core/sqlite_updates';

		preg_match_all('/-- \[(\d+?)\](.*?)-- \[\/\1\]/s', file_get_contents($update_file), $updates);

		$latest_version = max($updates[1]);
		if($current_version >= $latest_version) return;
		
		echo 'Autoupdating from version '.$current_version.' to '.$latest_version."..\n";
		
		for($i=$current_version+1;$i<=$latest_version;$i++) {
			echo 'Applying update '.$i.'.. ';
			
			switch($i) {
				case 7:
					$res = $this->database->query("SELECT `id`, `value` FROM `memory` WHERE is_array = 0");
					foreach($res as $row) {
						if(preg_match('/^[0-9]+$/', $row['value'])) {
							$new_value = (int)$row['value'];
						} elseif(preg_match('/^[0-9\.]+$/', $row['value'])) {
							$new_value = (float)$row['value'];
						} else {
							$new_value = $row['value'];
						}
						
						$this->database->query("UPDATE `memory` SET `value` = '".addslashes(serialize($new_value))."' WHERE id='".$row['id']."'");
					}
				break;
			}
			
			$sql = $updates[2][$i-1];
			$this->database->multiQuery($sql);
			echo "done\n";
		}
		
		$this->version = '3.'.$latest_version.'.x';
	}
	
	private function initServers() {
		$servers = $this->database->query('SELECT * FROM servers WHERE active=1');
		if(empty($servers)) die('Error: No servers defined (check mysql table `servers`)'."\n");
		foreach($servers as $data) {
			$this->connectServer($data);
		}
	}
	
	public function connectServer($data) {
		// Expects 1 full row of mysql table `servers`
		$Server = new IRC_Server($this, $data['id'], $data['host'], $data['port'], $data['ssl'], $data['bind']);
		if(!empty($data['password'])) $Server->setPass($data['password']);
		$Server->setUser(
			$data['my_username'],
			$data['my_hostname'],
			$data['my_servername'],
			$data['my_realname']
		);
		$Server->setNick($data['my_username']);
		
		$this->servers[$Server->id] = $Server;
	}
	
	private function createTempDir() {
		$this->tempDir = sys_get_temp_dir().'/nimda-'.libCrypt::getRandomHash();
		mkdir($this->tempDir);
		chmod($this->tempDir, 0700);
		mkdir($this->tempDir.'/cache');
		mkdir($this->tempDir.'/jobs');
		mkdir($this->tempDir.'/jobs_done');
	}
	
	private function removeTempDir() {
		shell_exec('rm -R '.$this->tempDir);
	}
	
	private function unloadPlugins() {
		foreach($this->plugins as $Plugin) {
			$Plugin->onUnload();
		}
	}
	
	public function getTempDir() {
		return $this->tempDir;
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
			$Plugin = new $classname($this, $this->database);
			$Plugin->onLoad();
			$this->plugins[$Plugin->id] = $Plugin;
		}
	}
	
	private function initJobs() {
		$this->jobsDoneDP = opendir($this->getTempDir().'/jobs_done');
	}
	
	private function triggerTimers() {
		if($this->time >= $this->timersLastTriggered+1) {
			$this->timerCount = 0;
			foreach($this->plugins as $Plugin) {
				$Plugin->Server  = false;
				$Plugin->Channel = false;
				$Plugin->User    = false;
				$Plugin->data    = false;
				if($Plugin->interval != 0) {
					$this->timerCount++;
					$Plugin->triggerTimer();
				}
			}
			
			$this->timersLastTriggered = $this->time;
		}
	}
	
	private function triggerJobs() {
		$jobs = array();
		while(false !== $file = readdir($this->jobsDoneDP)) {
			if($file{0} == '.') continue;
			$jobs[] = $file;
		}
		rewinddir($this->jobsDoneDP);
		
		foreach($jobs as $job) {
			$data = unserialize(file_get_contents($this->tempDir.'/jobs_done/'.$job));
			unlink($this->tempDir.'/jobs_done/'.$job);
			$this->jobCount--;
			
			$plugin  = &$data['origin']['plugin'];
			$server  = &$data['origin']['server'];
			$channel = &$data['origin']['channel'];
			$user    = &$data['origin']['user'];
			$command = &$data['origin']['command'];
			
			if(!isset($this->plugins[$plugin])) continue;
			$Plugin = $this->plugins[$plugin];
			
			if($server === false) {
				$Server  = false;
				$Channel = false;
				$User    = false;
			} elseif(!isset($this->servers[$server])) {
				continue;
			} else {
				$Server = $this->servers[$server];
				
				if($channel === false) {
					$Channel = false;
				} elseif(!isset($Server->channels[$channel])) {
					continue;
				} else {
					$Channel = $Server->channels[$channel];
				}
				
				if($user === false) {
					$User = false;
				} elseif(!isset($Server->users[$user])) {
					continue;
				} else {
					$User = $Server->users[$user];
				}
			}
			
			$Plugin->Server  = $Server;
			$Plugin->Channel = $Channel;
			$Plugin->User    = $User;
			$Plugin->data    = $data;
			$Plugin->command = $command;
			
			$Plugin->onJobDone();
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
			
			if(($Plugin->Channel || $Plugin->User) && $Plugin->getConfig('enabled') === 'no') continue;
			
			switch($command) {
				case '001':     $Plugin->onConnect(); break;
				case '311':     $Plugin->onWhoisReply(); break;
				case '315':     $Plugin->onMeJoin();  break;
				case '366':     $Plugin->onEndOfNames(); break; // TODO: Temp until onMode() is done
				case '433':     $Plugin->onNickAlreadyInUse(); break;
				case 'ERROR':   $Plugin->onMeQuit();  break;
				case 'JOIN':    if($User) $Plugin->onJoin(); break;
				case 'KICK':    if(isset($data['Victim'])) $Plugin->onKick(); else $Plugin->onMeKick(); break;
				case 'MODE':    if($Channel) $Plugin->onMode(); break;
				case 'NICK':    if($User) $Plugin->onNick(); else $Plugin->onMeNick(); break;
				case 'NOTICE':  if($User) $Plugin->onNotice(); else /* TODO: Add onServerNotice() */; break;
				case 'PART':    if($User) $Plugin->onPart(); else $Plugin->onMePart(); break;
				case 'PING':    $Plugin->onPing();    break;
				case 'PRIVMSG': $Plugin->onPrivmsg(); break;
				case 'TOPIC':   $Plugin->onTopic(); break;
				case 'QUIT':    $Plugin->onQuit(); break;
				
				// Virtual commands
				case 'vACTION': $Plugin->onAction(); break;
				case 'vCTCP': $Plugin->onCTCP(); break;
			}
		}
	}
	
	public function savePermanent($name, $value, $type='bot', $target='me') {
		if($this->getPermanent($name, $type, $target) === $value) return;
		
		$sql_value = serialize($value);
		
		if(false !== $this->getPermanent($name, $type, $target)) {
            $this->database->updatePermanent($name, $sql_value, $type, $target);
        } else {
            $this->database->insertPermanent($name, $sql_value, $type, $target);
        }
		$this->permanentVars[$type][$target][$name] = $value;
	}
	
	public function getPermanent($name, $type='bot', $target='me') {
		if(isset($this->permanentVars[$type][$target][$name])) {
			return $this->permanentVars[$type][$target][$name];
		}
		$value = $this->database->getPermanent($name,$type,$target);

		if($value === false) {
			$this->permanentVars[$type][$target][$name] = false;
			return false;
		}
		
		$value = unserialize($value);
		
		$this->permanentVars[$type][$target][$name] = $value;
		
	return $value;
	}
	
	public function removePermanent($name, $type='bot', $target='me') {
		if(isset($this->permanentVars[$type][$target][$name])) {
			unset($this->permanentVars[$type][$target][$name]);
		}
        $res = $this->database->removePermanent($name, $type, $target);
		if(!$res) return false; // nothing deleted
		
	return true;
	}
	
	public function cleanShutdown() {
		echo "Shutting down cleanly\n";
		die();
	}
	
	public function __destruct() {
		$this->unloadPlugins();
		$this->removeTempDir();
		$this->database->closeConnection();
	}
	
}

?>
