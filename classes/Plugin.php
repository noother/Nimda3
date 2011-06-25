<?php

abstract class Plugin {
	
	public $Bot;
	public $Server;
	public $Channel;
	public $User;
	public $data;
	public $command;
	
	protected $MySQL;
	protected $triggers = array();
	
	private $messageIsQuery;
	
	public final function __construct($Bot, $MySQL) {
		$this->Bot   = $Bot;
		$this->MySQL = $MySQL;
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
	
	// Events
	public function onLoad(){}
	
	/*
		All following events have object Server set
	*/
	
	public function onConnect(){
		/*
			IRC command "001"
			Triggered when the bot connects
			
			array data[
				string server          => the server's hostname
				string my_nick         => the final bots nick
				string welcome_message => The welcome message, the server sent
			]
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
				old_name => The nick the user had before
			]
		*/
	}
	
	public function onMeNick() {
		/*
			IRC command "NICK"
			Triggered when the bot changes its nick

			array data [
				old_name => The nick the bot had before
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
	
}

?>
