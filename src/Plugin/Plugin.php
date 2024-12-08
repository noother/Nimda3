<?php

namespace Nimda\Plugin;

use noother\Library\Validate;

abstract class Plugin {
	public $id;
	public $Bot;
	public $Server;
	public $Channel;
	public $User;
	public $MySQL;
	public $data = array();
	public $command;
	public $lastInterval;
	public $originalInstance;

	// You can override the following properties in plugins
	protected $config = array();
	public $enabledByDefault = true;
	public $triggers = array();
	public $interval = 0;
	public $helpTriggers = false;
	public $helpCategory = 'Misc';
	public $helpText = 'There is no help available for this command.';
	public $hideFromHelp = false;
	public $usage = false;

	public function __construct($Bot, $MySQL) {
		$type = explode('\\', static::class)[2];
		$classname = $this->getName();

		if(!isset($this->originalInstance)) $this->originalInstance = $this;
		$this->id           = strtolower($classname);
		$this->Bot          = $Bot;
		$this->MySQL        = $MySQL;
		$this->lastInterval = time();
		
		$this->config['enabled'] = array(
			'type'        => 'enum',
			'options'     => array('yes', 'no'),
			'default'     => $this->enabledByDefault ? 'yes' : 'no',
			'description' => 'Determines if this Plugin is enabled for this channel / user'
		);
	}

	public function getName(): string {
		return substr((new \ReflectionClass($this))->getShortName(), 0, -6); // Remove "Plugin" from the end
	}

	public function reload(): void {
		$Reflector = new \ReflectionClass($this->originalInstance);

		$old_filename = $Reflector->getFilename();
		$old_shortname = $Reflector->getShortName();
		echo "Reloading $old_shortname\n";
		$old_content = file_get_contents($old_filename);

		$new_shortname = $old_shortname.'_rehashed_'.md5(rand());
		$new_classname = $Reflector->getNamespaceName().'\\'.$new_shortname;
		$new_filename = "tmp/$new_shortname.php";
		$new_content = preg_replace("/$old_shortname\s+extends\s+Plugin/", "$new_shortname extends Plugin", $old_content);

		file_put_contents($new_filename, $new_content);
		require_once($new_filename);
		unlink($new_filename);

		$this->onUnload();

		$Plugin = new $new_classname($this->Bot, $this->MySQL);
		$Plugin->id = $this->id;
		$Plugin->originalInstance = $this->originalInstance;

		$Plugin->onLoad();

		$this->Bot->plugins[$this->id] = $Plugin;
	}

	protected final function reply($string) {
		switch($this->command) {
			case 'PRIVMSG':
				if($this->Channel) $this->Channel->privmsg($string);
				else $this->User->privmsg($string);
			break;
			case '315': case 'JOIN': case 'PART':
				$this->Channel->privmsg($string);
			break;
			case 'NICK':
				$this->User->privmsg($string);
			break;
			case 'KICK':
				if(isset($this->data['Victim'])) $this->Channel->privmsg($string);
				else $this->User->privmsg($string);
			break;
			default:
				echo 'Error - No reply-rule set for '.$this->command."\n";
			break;
		}
	}
	
	public final function addJob($callback, $data=null) {
		$filename = md5(rand()).'.json';
		$data = array(
			'classname'         => $this->originalInstance::class,
			'origin'            => array(
				'plugin'            => $this->id,
				'server'            => ($this->Server ? $this->Server->id : false),
				'channel'           => ($this->Channel ? $this->Channel->id : false),
				'user'              => ($this->User ? $this->User->id : false),
				'command'           => ($this->command ? $this->command : false)
			),
			'callback'          => $callback,
			'job_done_filename' => 'tmp/jobs_done/'.$filename,
			'data'              => $data
		);
		
		$filepath = 'tmp/jobs/'.$filename;
		
		file_put_contents($filepath, json_encode($data));
		shell_exec('./startjob '.escapeshellarg($filepath).' >/dev/null &');
		
		$this->Bot->jobCount++;
		if($this->Bot->jobCount > $this->Bot->jobCountMax) $this->Bot->jobCountMax = $this->Bot->jobCount;
	}
	
	public final function saveVar($name, $value) {
		$this->Bot->savePermanent($name, $value, 'plugin', $this->id);
	}
	
	public final function getVar($name, $default=false) {
		$value = $this->Bot->getPermanent($name, 'plugin', $this->id);
		if($value === false) return $default;
		
	return $value;
	}
	
	public final function removeVar($name) {
		$this->Bot->removePermanent($name, 'plugin', $this->id);
	}
	
	public final function getConfigList() {
		$config = array();
		foreach($this->config as $name => $def) {
			$config[$name] = $def;
			$config[$name]['value'] = $this->getConfig($name);
		}
		
	return $config;
	}
	
	public final function getConfig($name) {
		if(!isset($this->config[$name])) return false;
		
		if($this->Channel === false) {
			$Target = $this->User;
		} else {
			$Target = $this->Channel;
		}
		
	return $Target->getVar('config_'.$this->id.'_'.$name, $this->config[$name]['default']);
	}
	
	public final function setConfig($name, $value) {
		if(!isset($this->config[$name])) return false;
		
		if($this->Channel === false) {
			$Target = $this->User;
		} else {
			$Target = $this->Channel;
		}
		
		$identifier = 'config_'.$this->id.'_'.$name;
		$def = $this->config[$name];
		
		if(!isset($def['type'])) $def['type'] = 'string';
		
		switch($def['type']) {
			case 'enum':
				if(array_search($value, $def['options']) === false) return false;
				$Target->saveVar($identifier, $value);
			break;
			case 'int':
				if(!Validate::integer($value)) return false;
				$Target->saveVar($identifier, (int)$value);
			break;
			case 'range':
				if(!Validate::integer($value)) return false;
				if($value < $def['min'] || $value > $def['max']) return false;
				$Target->saveVar($identifier, (int)$value);
			break;
			case 'unsigned_int':
				if(!Validate::integer($value, true)) return false;
				$Target->saveVar($identifier, (int)$value);
			break;
			case 'string':
				$Target->saveVar($identifier, $value);
			break;
			default:
				return false; // invalid type
		}
		
	return true;
	}
	
	public final function getEnabledChannels() {
		return $this->getChannelsWithConfig('enabled');
	}
	
	public final function sendToEnabledChannels($message) {
		$this->sendToChannelsWithConfig('enabled', 'yes', $message);
	}
	
	public final function getChannelsWithConfig($config, $value='yes') {
		$channels = array();
		
		foreach($this->Bot->servers as $Server) {
			foreach($Server->channels as $Channel) {
				if($Channel->getVar('config_'.$this->id.'_'.$config, $this->config[$config]['default']) === $value) {
					$channels[] = $Channel;
				}
			}
		}
	
	return $channels;
	}
	
	public final function sendToChannelsWithConfig($config, $value, $message) {
		$channels = $this->getChannelsWithConfig($config, $value);
		foreach($channels as $Channel) {
			$Channel->privmsg($message);
		}
	}
	
	public final function printUsage() {
		if(false === $usage = $this->getUsage()) return false;
		$this->reply($usage);
	}
	
	public final function getUsage() {
		if($this->usage === false) return false;
		return "\x02Usage:\x02 ".$this->data['trigger'].(!empty($this->usage) ? ' '.$this->usage : '');
	}
	
	public final function findPlugin($string) {
		if(false === $Plugin = $this->getPluginById($string)) {
			$Plugin = $this->getPluginByTrigger($string);
		}
		
	return $Plugin;
	}
	
	public final function getPluginByTrigger($trigger) {
		foreach($this->Bot->plugins as $Plugin) {
			if(array_search($trigger, $Plugin->triggers) !== false || ($trigger[0] != '!' && array_search('!'.$trigger, $Plugin->triggers) !== false)) {
				$Plugin->Server  = $this->Server;
				$Plugin->Channel = $this->Channel;
				$Plugin->User    = $this->User;
				return $Plugin;
			}
		}
	
	return false;
	}
	
	public final function getPluginById($id) {
		$id = strtolower($id);
		foreach($this->Bot->plugins as $Plugin) {
			if($Plugin->id == $id) {
				$Plugin->Server  = $this->Server;
				$Plugin->Channel = $this->Channel;
				$Plugin->User    = $this->User;
				return $Plugin;
			}
		}
	
	return false;
	}
	
	public function getHelpText() {
		return $this->helpText;
	}
	
	// Events
	public function onLoad() {
		/*
			Triggered once when the bot starts
		*/
	}
	
	public function onUnload() {
		/*
			Triggered once when the bot process quits
		*/
	}
	
	public function onInterval() {
		/*
			Triggered every $this->interval seconds, if interval is not 0
		*/
	}
	
	public function onJobDone() {
		/*
			Triggered when a job has been processed and output is ready
			
			object Channel
			object User
			
			array data [
				mixed result    => The data the callback of addJob() returned
				string callback => The callback function from where this data got returned
			]
		*/
	}
	
	
	/*
		All following events have object Server set
	*/
	
	public function onConnect() {
		/*
			IRC command "001"
			Triggered when the bot connects
			
			array data [
				string server          => the server's hostname
				string my_nick         => the final bots nick
				string welcome_message => The welcome message, the server sent
			]
		*/
	}
	
	public function onEndOfNames() {
		/*
			IRC command "366"
			TODO: Temp - Triggered after a MODE has been set and user modes have been re-read
			
			object Channel
		*/
	}
	
	public function onJoin() {
		/*
			IRC command "JOIN"
			Triggered when a user joins a channel
			
			object Channel
			object User
		*/
	}

	public function onMeJoin() {
		/*
			IRC command "315"
			Triggered when the bot finished joining a channel and instanciated all users
			
			object Channel
		*/
	}
	
	public function onKick() {
		/*
			IRC command "KICK"
			Triggered when a user gets kicked from a channel
			
			object Channel
			object User
			
			data [
				object Victim       => The kicked user
				string kick_message => The kick message
			]
		*/
	}
	
	public function onMeKick() {
		/*
			IRC command "KICK"
			Triggered when the bot gets kicked from a channel
			
			object Channel
			object User
			
			data [
				string kick_message => The kick message
			]
		*/
	}
	
	public function onNick() {
		/*
			IRC command "NICK"
			Triggered when a user changes his nick
			
			object User
			
			array data [
				string old_nick => The nick the user had before
			]
		*/
	}
	
	public function onMeNick() {
		/*
			IRC command "NICK"
			Triggered when the bot changes its nick

			array data [
				string old_nick => The nick the bot had before
			]
		*/
	}
	
	public function onMode() {
		/*
			IRC command "MODE"
			Triggered when a mode for a user in a channel is changed
			
			object User
			object Channel
			
			array data [
				string mode => The mode that has been set for Victim
				object Victim => The user the mode change targeted
			]
		*/
	}
	
	public function onNickAlreadyInUse() {
		/*
			IRC command "433"
			Triggered on connect if nickname is already in use
			
			array data [
				string nick => The nickname that the bot has tried to use
			]
		*/
	}
	
	public function onNotice() {
		/*
			IRC command "NOTICE"
			Triggered when a user sends a NOTICE to the bot
			
			object User
			
			array data [
				string text => The text sent with the NOTICE
			]
		*/
	}
	
	public function onPart() {
		/*
			IRC command "PART"
			Triggered when a user parts a channel
			
			object Channel
			object User
			
			array data [
				string part_message => The part message the user sent.
				                       (only if a part message has been sent)
			]
		*/
	}

	public function onMePart() {
		/*
			IRC command "PART"
			Triggered when the bot parts a channel
			
			object Channel
			
			array data [
				string part_message => The part message the bot sent.
				                       (only if a part message has been sent)
			]
		*/
	}
	
	public function onPing() {
		/*
			IRC command "PING"
			Triggered when the server sends PING to see if we're still alive
			
			array data [
				string challenge => The string that has to be sent back to the server via PONG
			]
			
		*/
	}
	
	public function onTopic() {
		/*
			IRC command "TOPIC"
			Triggered when a user changes the topic
			
			object Channel
			object User
			
			array data [
				string topic => The new channel topic
			]
			
		*/
	}
	
	public function onQuit() {
		/*
			IRC command "QUIT"
			Triggered when a user quits the server, and the bot is in a channel the user was in
			
			object User
			array data [
				string quit_message => The quit message the user sent
			]
		*/
	}
	
	public function onMeQuit() {
		/*
			IRC command "QUIT"
			Triggered when the bot quits the server
			
			nothing
		*/
	}
	
	public function onMessage() {
		/*
			IRC command "PRIVMSG"
			Triggered when a message is sent to a channel where the bot is in, or to the bot itself
			
			object User
			object Channel (only if it's a channel message)
			
			array data [
				string text  => The text sent by the User
				bool isQuery => true, if it's a private message, false if it's a channel message
			]
		*/
	}
	
	public function onChannelMessage() {
		/*
			IRC command "PRIVMSG"
			Triggered when a message is sent to a channel where the bot is in
			
			object User
			object Channel
			
			array data [
				string text => The text sent by the User
			]
		*/
	}
	
	public function onQuery() {
		/*
			IRC command "PRIVMSG"
			Triggered when a message is sent to the bot itself
			
			object User
			
			array data [
				string text => The text sent by the User
			]
		*/
	}
	
	public function onAction() {
		/*
			IRC command "PRIVMSG \x01ACTION (..)\x01"
			Triggered when a user sends an action (/me)
			
			object User
			object Channel (only if the ACTION is sent to a channel)
			
			array data [
				string text  => The text sent by the User
				bool isQuery => True if the action is sent in query, otherwise false
			]
		*/
	}
	
	public function onCTCP() {
		/*
			IRC command "PRIVMSG \x01(..) [..]\x01"
			Triggered when a user sends a CTCP request (PING, VERSION, etc.)
			
			object User
			object Channel (only if the CTCP request is sent to a channel)
			
			array data [
				string ctcp_command => the command sent (PING, VERSION, etc.)
				string text  => The text sent with the CTCP
				bool isQuery => True if the action is sent in query, otherwise false
			]
		*/
	}
	
	public function onWhoisReply() {
		/*
			IRC command "WHOIS"
			Triggered on server's reply to our WHOIS command
			
			object User
		*/
	}
	
	public function isTriggered() {
		/*
			IRC command "PRIVMSG"
			Triggered if the first part of the messages matches a defined trigger
			
			object User
			object Channel (only if it's a channel message)
			
			array data [
				string trigger => The trigger that has been used
				string text    => The text sent by the User, without the trigger
				                  (only if the message doesn't only contain the trigger)
				bool isQuery   => true, if it's a private message, false if it's a channel message
			]
		*/
	}
	
	public final function onPrivmsg() {
		$this->onMessage();
		$isQuery = $this->data['isQuery']; 
		
		unset($this->data['isQuery']);
		if($isQuery) $this->onQuery();
		else $this->onChannelMessage();
		
		$this->data['isQuery'] = $isQuery;
		
		foreach($this->triggers as $trigger) {
			$trigger_len = strlen($trigger);
			
			if(str_starts_with($this->data['text'], $trigger)) {
				if($trigger_len == strlen($this->data['text'])) {
					$this->data['trigger'] = $trigger;
					unset($this->data['text']);
					$this->isTriggered();
					break;
				} elseif($this->data['text'][$trigger_len] == ' ') {
					$this->data['trigger'] = $trigger;
					$this->data['text']    = substr($this->data['text'], $trigger_len+1);
					$this->isTriggered();
					break;
				}
			}
		}
	}
	
	public final function triggerTimer() {
		if(!$this->interval) return;
		
		if($this->Bot->time >= $this->lastInterval+$this->interval) {
			$this->onInterval();
			$this->lastInterval = $this->Bot->time;
		}
	}
	
}

?>
