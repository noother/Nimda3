<?php

namespace Nimda;

use noother\Database\MySQL;
use Nimda\IRC\Server;

class Nimda {
	protected static $Instance;

	public $servers = array();
	public $plugins = array();
	public $MySQL;
	public $version;

	public $timerCount;
	public $jobCount;
	public $jobCountMax;

	private $time;
	private $timersLastTriggered;
	private $permanentVars = array();
	private $jobsDoneDP;

	public function __construct() {
		pcntl_async_signals(true);
		pcntl_signal(SIGINT,   array($this, 'cleanShutdown'));
		pcntl_signal(SIGTERM,  array($this, 'cleanShutdown'));

		static::$Instance = $this;
	}

	public static function getInstance(): static {
		return static::$Instance;
	}

	public function run(): never {
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

	public function getTime(): int {
		return $this->time;
	}

	private function initBot(): void {
		$this->MySQL = new MySQL(
			Configure::read('mysql.host', 'localhost'),
			Configure::read('mysql.user', 'nimda3'),
			Configure::read('mysql.pass', 'nimda3'),
			Configure::read('mysql.db',   'nimda3'),
			Configure::read('mysql.port', 3306),
		);

		$this->autoUpdateSQL();

		$this->initJobs();
		$this->initPlugins();
		$this->initServers();
		$this->timersLastTriggered = time()+10; // Give him some time to do the connecting before triggering the timers
	}
	
	private function autoUpdateSQL() {
		echo "Checking for updates..\n";
		$tmp = $this->MySQL->query('SHOW TABLES LIKE "version"');
		if(empty($tmp)) $current_version = 0;
		else $current_version = $this->MySQL->fetchColumn("SELECT `version` FROM `version`");
		if($current_version === false) die("Error: Table version exists but has no entry.\n");
		$this->version = '3.'.$current_version.'.x Reboot';
		
		preg_match_all('/-- \[(\d+?)\](.*?)-- \[\/\1\]/s', file_get_contents('sql_updates.sql'), $updates);
		
		$latest_version = max($updates[1]);
		if($current_version >= $latest_version) return;
		
		echo 'Autoupdating from version '.$current_version.' to '.$latest_version."..\n";
		
		for($i=$current_version+1;$i<=$latest_version;$i++) {
			echo 'Applying update '.$i.'.. ';
			
			switch($i) {
				case 7:
					$res = $this->MySQL->query("SELECT `id`, `value` FROM `memory` WHERE is_array = 0");
					foreach($res as $row) {
						if(preg_match('/^[0-9]+$/', $row['value'])) {
							$new_value = (int)$row['value'];
						} elseif(preg_match('/^[0-9\.]+$/', $row['value'])) {
							$new_value = (float)$row['value'];
						} else {
							$new_value = $row['value'];
						}
						
						$this->MySQL->query("UPDATE `memory` SET `value` = '".addslashes(serialize($new_value))."' WHERE id='".$row['id']."'");
					}
				break;
			}
			
			$sql = $updates[2][$i-1];
			$sqls = explode(';', $sql);
			$this->MySQL->multiQuery($sqls);
			echo "done\n";
		}
		
		$this->version = '3.'.$latest_version.'.x';
	}
	
	private function initServers() {
		$servers = $this->MySQL->query('SELECT * FROM servers WHERE active=1');
		if(empty($servers)) die('Error: No servers defined (check mysql table `servers`)'."\n");
		foreach($servers as $data) {
			$this->connectServer($data);
		}
	}
	
	public function connectServer($data) {
		// Expects 1 full row of mysql table `servers`
		$Server = new Server($data['id'], $data['host'], $data['port'], $data['ssl'], $data['bind'], $data['sasl']);
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

	private function cleanupTemp() {
		$files = glob('tmp/{jobs,jobs_done}/*', GLOB_BRACE);
		foreach($files as $file) {
			unlink($file);
		}
	}

	private function unloadPlugins() {
		foreach($this->plugins as $Plugin) {
			$Plugin->onUnload();
		}
	}

	private function initPlugins() {
		$files = glob('src/Plugin/*/*.php');
		foreach($files as $file) {
			$classname = 'Nimda\\'.str_replace('/', '\\', substr($file, 4, -4)); // remove src/ from the beginning & .php from the end

			$tmp = explode('\\', $classname);
			$type = $tmp[2];
			$name = substr($tmp[3], 0, -6); // Remove Plugin from the end
			echo "Loading $type plugin $name..\n";

			require_once($file);
			$Plugin = new $classname($this->MySQL);
			$Plugin->onLoad();
			$this->plugins[$Plugin->id] = $Plugin;
		}
	}

	private function initJobs() {
		$this->jobsDoneDP = opendir('tmp/jobs_done');
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
	
	private function triggerJobs(): void {
		$jobs = array();
		while(false !== $file = readdir($this->jobsDoneDP)) {
			if($file[0] == '.') continue;
			$jobs[] = $file;
		}
		rewinddir($this->jobsDoneDP);
		
		foreach($jobs as $job) {
			$data = json_decode(file_get_contents('tmp/jobs_done/'.$job), true);
			unlink('tmp/jobs_done/'.$job);
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
			$sql = "
				UPDATE
					`memory`
				SET
					`value` = '".addslashes($sql_value)."',
					`modified` = NOW()
				WHERE
					`type`   = '".addslashes($type)."' AND
					`target` = '".addslashes($target)."' AND
					`name`   = '".addslashes($name)."'
			";
		} else {
			$sql = "
				INSERT INTO
					`memory` (`name`, `type`, `target`, `value`, `created`, `modified`)
				VALUES (
					'".addslashes($name)."',
					'".addslashes($type)."',
					'".addslashes($target)."',
					'".addslashes($sql_value)."',
					NOW(),
					NOW()
			)";
		}
		
		$this->MySQL->query($sql);
		
		$this->permanentVars[$type][$target][$name] = $value;
	}
	
	public function getPermanent($name, $type='bot', $target='me') {
		if(isset($this->permanentVars[$type][$target][$name])) {
			return $this->permanentVars[$type][$target][$name];
		}
		
		$value = $this->MySQL->fetchColumn("
			SELECT
				`value`
			FROM
				`memory`
			WHERE
				`type`   = '".addslashes($type)."' AND
				`target` = '".addslashes($target)."' AND
				`name`   = '".addslashes($name)."'
		");
		
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
		
		$sql = "
			DELETE FROM
				`memory`
			WHERE
				`type`   = '".addslashes($type)."' AND
				`target` = '".addslashes($target)."' AND
				`name`   = '".addslashes($name)."'
		";
		
		$res = $this->MySQL->query($sql);
		if(!$res) return false; // nothing deleted
		
	return true;
	}
	
	public function cleanShutdown(int $signo, mixed $siginfo): void {
		echo "Shutting down cleanly\n";
		die();
	}
	
	public function __destruct() {
		$this->unloadPlugins();
		$this->cleanupTemp();
	}
	
}

?>
