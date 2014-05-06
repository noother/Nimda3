<?php
// Depends on Plugin_Seen
class Plugin_Lockdown extends Plugin {
	
	public $triggers = array('!lockdown');
	
	public $helpTriggers = array('!lockdown');
	public $helpText = 'Locks down the channel by setting the moderated flag and voicing all known users. New users are asked to reply with a text to get voice. Useful for bot invasions.';
	
	private $channelLockdownCount = 0;
	
	function isTriggered() {
		if(!$this->Channel) {
			$this->reply('This command only works in a channel.');
			return;
		}
		
		if($this->User->mode != '@') {
			$this->reply('You have to be an operator in this channel to use '.$this->data['trigger'].'.');
			return;
		}
		
		if($this->Server->Me->modes[$this->Channel->id] != '@') {
			$this->reply('I need operator status to initiate a channel lockdown.');
			return;
		}
		
		if(!isset($this->Channel->lockdownActive)) {
			$this->startChannelLockdown();
		} else {
			$this->stopChannelLockdown();
		}
	}
	
	function onInterval() {
		foreach($this->Bot->servers as $Server) {
			foreach($Server->channels as $Channel) {
				if(!$Channel->lockdownActive) continue;
				
				foreach($Channel->voiceQueue as $key => $User) {
					// If he got a mode meanwhile (by chanserv probably) don't voice him again
					if(!empty($User->modes[$Channel->id])) continue;
					
					$Channel->setUserMode('+v', $User->nick);
					$Channel->voicedForLockdown[] = $User;
					unset($Channel->voiceQueue[$key]);
				}
				
			}
		}
	}
	
	private function startChannelLockdown() {
		$this->reply('Initiating channel lockdown.');
		
		$this->Channel->lockdownActive = true;
		
		$this->Channel->voicedForLockdown = array();
		foreach($this->Channel->users as $User) {
			if(empty($User->modes[$this->Channel->id])) {
				$this->Channel->voicedForLockdown[] = $User;
			}
		}
		
		$modes = array();
		foreach($this->Channel->voicedForLockdown as $User) {
			$modes[] = array('mode' => '+v', 'user' => $User->nick);
		}
		
		$this->Channel->setUserModes($modes);
		$this->Channel->setMode('+m');
		
		$this->Channel->voiceQueue = array();
		$this->Channel->waitingForNotice = array();
		
		$this->Channel->oldTopic = $this->Channel->topic;
		$this->Channel->setTopic($this->Channel->topic.' | CHANNEL LOCKDOWN ACTIVE');
		
		$this->channelLockdownCount++;
		$this->interval = 5;
	}
	
	private function stopChannelLockdown() {
		$this->reply('Ending channel lockdown.');
		
		$modes = array();
		foreach($this->Channel->voicedForLockdown as $User) {
			$modes[] = array('mode' => '-v', 'user' => $User->nick);
		}
		unset($this->Channel->voicedForLockdown);
		$this->Channel->setUserModes($modes);
		$this->Channel->setMode('-m');
		
		$this->Channel->setTopic($this->Channel->oldTopic);
		unset($this->Channel->oldTopic);
		
		unset($this->Channel->voiceQueue);
		unset($this->Channel->waitingForNotice);
		unset($this->Channel->lockdownActive);
		
		$this->channelLockdownCount--;
		if($this->channelLockdownCount == 0) $this->interval = 0;
	}
	
	function onJoin() {
		if(!isset($this->Channel->lockdownActive)) return;
		
		$been_here_before = $this->Bot->plugins['seen']->getVar($this->User->id) !== false;
		if($been_here_before) {
			// Add user to to-voice queue and voice him in the next 5 seconds to prevent voicing users that get a mode by chanserv already
			$this->Channel->voiceQueue[] = $this->User;
		} else {
			$this->User->notice("Hi! This channel is currently under lockdown due to a spamming problem. As this is your first visit here you're currently not able to talk in the channel. Please just type \"/notice ".$this->Server->Me->nick." I am not a bot\" into your IRC client and I'll resolve this issue right away. Thank you!");
			$this->Channel->waitingForNotice[$this->User->id] = $this->User;
		}
	}
	
	function onNotice() {
		if($this->data['text'] != 'I am not a bot') return;
		
		foreach($this->Server->channels as $Channel) {
			if(!$Channel->lockdownActive) continue;
			if(!isset($Channel->waitingForNotice[$this->User->id])) continue;
			
			$Channel->setUserMode('+v', $this->User->nick);
			unset($Channel->waitingForNotice[$this->User->id]);
			$Channel->voicedForLockdown[] = $this->User;
		}
	}
	
}

?>

