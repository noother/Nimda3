<?php

abstract class Plugin {
	
	public $id;
	public $Bot;
	public $Server;
	public $Channel;
	public $User;
	public $MySQL;
	public $data;
	public $command;
	public $lastInterval;
	
	private $messageIsQuery;
	
	// You can override the following properties in plugins
	protected $config = array();
	protected $enabledByDefault = true;
	public $triggers = array();
	public $interval = 0;
	
	
	
	public final function __construct($Bot, $MySQL) {
		list($crap, $plugin_name) = explode('_', get_class($this), 2);
		$this->id           = strtolower($plugin_name);
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
	
	protected final function reply($string) {
		switch($this->command) {
			case 'PRIVMSG':
				if($this->messageIsQuery) $this->User->privmsg($string);
				else $this->Channel->privmsg($string);
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
		$filename = $this->id.'_'.
		            $this->Server->id.'_'.
		            ($this->Channel ? $this->Channel->id : 'none').'_'.
		            ($this->User ? $this->User->id : 'none').'_'.
		            libCrypt::getRandomHash();
		
		$data = array(
			'classname'         => get_class($this),
			'callback'          => $callback,
			'plugin_path'       => 'plugins/'.(libString::startsWith('CorePlugin', get_class($this))?'core':'user').'/'.get_class($this).'.php',
			'job_done_filename' => $this->Bot->getTempDir().'/jobs_done/'.$filename,
			'data'              => $data
		);
		
		$filepath = $this->Bot->getTempDir().'/jobs/'.$filename;
		
		file_put_contents($filepath, serialize($data));
		shell_exec('/usr/bin/php ./core/Job.php '.escapeshellarg($filepath).' > /dev/null &');
	}
	
	public final function saveVar($name, $value) {
		$this->Bot->savePermanent($name, $value, 'plugin', $this->id);
	}
	
	public final function getVar($name) {
		return $this->Bot->getPermanent($name, 'plugin', $this->id);
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
		
		
		$config = $Target->getVar('config_'.$this->id.'_'.$name);
		
		if($config === false) {
			$config = $this->config[$name]['default'];
		}
		
	return $config;
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
		
		switch($def['type']) {
			case 'enum':
				if(array_search($value, $def['options']) !== false) {
					$Target->saveVar($identifier, $value);
				} else {
					return false;
				}
			break;
		}
		
	return true;
	}
	
	public final function getEnabledChannels() {
		$channels = array();
		
		foreach($this->Bot->servers as $Server) {
			foreach($Server->channels as $Channel) {
				if($Channel->getVar('config_'.$this->id.'_enabled') === 'yes') {
					$channels[] = $Channel;
				}
			}
		}
	
	return $channels;
	}
	
	public final function sendToEnabledChannels($message) {
		$channels = $this->getEnabledChannels();
		foreach($channels as $Channel) {
			$Channel->privmsg($message);
		}
	}
	
	// Events
	public function onLoad() {
		/*
			Triggered once when the bot starts
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
				old_nick => The nick the user had before
			]
		*/
	}
	
	public function onMeNick() {
		/*
			IRC command "NICK"
			Triggered when the bot changes its nick

			array data [
				old_nick => The nick the bot had before
			]
		*/
	}
	
	public function onNickAlreadyInUse() {
		/*
			IRC command "433"
			Triggered on connect if nickname is already in use
			
			array data [
				nick => The nickname that the bot has tried to use
			]
		*/
	}
	
	public function onNotice() {
		/*
			IRC command "NOTICE"
			Triggered when a user sends a NOTICE to the bot
			
			object User
			
			array data [
				text => The text sent with the NOTICE
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
		$this->messageIsQuery = $this->data['isQuery'];
		
		$this->onMessage();
		
		foreach($this->triggers as $trigger) {
			$trigger_len = strlen($trigger);
			
			if($trigger == substr($this->data['text'], 0, $trigger_len)) {
				if($trigger_len == strlen($this->data['text'])) {
					$this->data['trigger'] = $trigger;
					unset($this->data['text']);
					$this->isTriggered();
					break;
				} elseif($this->data['text']{$trigger_len} == ' ') {
					$this->data['trigger'] = $trigger;
					$this->data['text']    = substr($this->data['text'], $trigger_len+1);
					$this->isTriggered();
					break;
				}
			}
		}
		
		unset($this->data['isQuery']);
		if($this->messageIsQuery) $this->onQuery();
		else $this->onChannelMessage();
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
