<?php
// Depends on Plugin_Seen
class Plugin_Lockdown extends Plugin {
	
	public $triggers = array('!lockdown');
	
	public $helpTriggers = array('!lockdown');
	public $helpText = 'Locks down the channel by setting the moderated flag and voicing all known users. New users are asked to reply with a text to get voice. Type the command again to revert channel lockdown. Useful for bot invasions.';
	
	public $interval = 0; // Gets activated when a channel lockdown is started and deactivated when all channel lockdowns are disabled
	
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
		
		if(!$this->Channel->getVar('lockdown_active')) {
			$this->startChannelLockdown();
		} else {
			$this->stopChannelLockdown();
		}
	}
	
	function onMeJoin() {
		if($this->Channel->getVar('lockdown_active')) {
			$this->channelLockdownCount++;
			$this->interval = 5;
		}
	}
	
	function onInterval() {
		foreach($this->Bot->servers as $Server) {
			foreach($Server->channels as $Channel) {
				if(!$Channel->getVar('lockdown_active')) continue;
				
				$users = array();
				foreach($Channel->users as $User) {
					if(empty($User->modes[$Channel->id]) && $this->findPlugin('seen')->getVar($User->id) !== false) {
						$users[] = $User;
					}
				}
				$this->voiceUsers($Channel, $users);
			}
		}
	}
	
	private function startChannelLockdown() {
		$this->reply('Initiating channel lockdown.');
		
		$this->Channel->saveVar('lockdown_active', true);
		
		$users = array();
		foreach($this->Channel->users as $User) {
			if(empty($User->modes[$this->Channel->id])) {
				$users[] = $User;
			}
		}
		$this->voiceUsers($this->Channel, $users);
		
		$this->Channel->setMode('+m');
		
		$this->Channel->saveVar('lockdown_old_topic', $this->Channel->topic);
		$this->Channel->setTopic($this->Channel->topic.' | CHANNEL LOCKDOWN ACTIVE');
		
		$this->channelLockdownCount++;
		$this->interval = 5;
		$this->lastInterval = $this->Bot->time;
	}
	
	private function stopChannelLockdown() {
		$this->reply('Ending channel lockdown.');
		
		$usermodes = array();
		foreach($this->Channel->getVar('voiced_during_lockdown', array()) as $nick) {
			$usermodes[] = array('mode' => '-v', 'user' => $nick);
		}
		$this->Channel->setUserModes($usermodes);
		
		$this->Channel->setMode('-m');
		
		if($this->Channel->getVar('lockdown_old_topic')) $this->Channel->setTopic($this->Channel->getVar('lockdown_old_topic'));
		
		$this->Channel->removeVar('voiced_during_lockdown');
		$this->Channel->removeVar('lockdown_old_topic');
		$this->Channel->removeVar('lockdown_active');
		
		$this->channelLockdownCount--;
		if($this->channelLockdownCount == 0) $this->interval = 0;
	}
	
	function onJoin() {
		if(!$this->channelLockdownCount || !$this->Channel->getVar('lockdown_active')) return;
		
		if($this->findPlugin('seen')->getVar($this->User->id) === false) {
			$this->User->notice("Hi! This channel is currently under lockdown due to a spamming problem. As this is your first visit here you're currently not able to talk in the channel. Please just type \"/notice ".$this->Server->Me->nick." I am not a bot\" into your IRC client and I'll resolve this issue right away. Thank you!");
		}
	}
	
	function onNotice() {
		if(!$this->channelLockdownCount || $this->data['text'] != 'I am not a bot') return;
		
		foreach($this->Server->channels as $Channel) {
			if($Channel->getVar('lockdown_active') && isset($Channel->users[$this->User->id]) && empty($this->User->modes[$Channel->id])) {
				$this->voiceUser($Channel, $this->User);
				$this->injectSeenJoin($Channel, $this->User);
			}
		}
	}
	
	function onMode() {
		if(!$this->channelLockdownCount || !$this->Channel->getVar('lockdown_active')) return;
		
		if(!isset($this->Channel->users[$this->User->id])) return; // Don't do anything if the mode was set by a non-channel-user, like ChanServ
		
		if($this->findPlugin('seen')->getVar($this->data['Victim']->id) === false) {
			$this->injectSeenJoin($this->Channel, $this->data['Victim']);
		}
	}
	
	function onNick() {
		if(!$this->channelLockdownCount) return;
		
		$old_id = strtolower($this->data['old_nick']);
		
		foreach($this->Server->channels as $Channel) {
			if($Channel->getVar('lockdown_active')) {
				$voiced = $Channel->getVar('voiced_during_lockdown', array());
				if(isset($voiced[$old_id])) {
					unset($voiced[$old_id]);
					$voiced[$this->User->id] = $this->User->nick;
					$Channel->saveVar('voiced_during_lockdown', $voiced);
				}
			}
		}
	}
	
	function onKick() {
		if(!$this->channelLockdownCount || !$this->Channel->getVar('lockdown_active')) return;
		
		$voiced = $this->Channel->getVar('voiced_during_lockdown', array());
		if(isset($voiced[$this->data['Victim']->id])) {
			unset($voiced[$this->data['Victim']->id]);
			$this->Channel->saveVar('voiced_during_lockdown', $voiced);
		}
	}
	
	function onPart() {
		if(!$this->channelLockdownCount || !$this->Channel->getVar('lockdown_active')) return;
		
		$voiced = $this->Channel->getVar('voiced_during_lockdown', array());
		if(isset($voiced[$this->User->id])) {
			unset($voiced[$this->User->id]);
			$this->Channel->saveVar('voiced_during_lockdown', $voiced);
		}
	}
	
	function onTopic() {
		if(!$this->channelLockdownCount || !$this->Channel->getVar('lockdown_active')) return;
		
		if($this->User->id != $this->Server->Me->id) $this->Channel->removeVar('lockdown_old_topic');
	}
	
	function onQuit() {
		if(!$this->channelLockdownCount) return;
		
		foreach($this->Server->channels as $Channel) {
			if($Channel->getVar('lockdown_active')) {
				$voiced = $Channel->getVar('voiced_during_lockdown', array());
				if(isset($voiced[$this->User->id])) {
					unset($voiced[$this->User->id]);
					$Channel->saveVar('voiced_during_lockdown', $voiced);
				}
			}
		}
	}
	
	private function voiceUsers($Channel, $users) {
		$voiced = $Channel->getVar('voiced_during_lockdown', array());
		$usermodes = array();
		foreach($users as $User) {
			$usermodes[] = array('mode' => '+v', 'user' => $User->nick);
			if(!isset($voiced[$User->id])) {
				$voiced[$User->id] = $User->nick;
			}
		}
		
		$Channel->setUserModes($usermodes);
		$Channel->saveVar('voiced_during_lockdown', $voiced);
	}
	
	private function voiceUser($Channel, $User) {
		$this->voiceUsers($Channel, array($User));
	}
	
	private function injectSeenJoin($Channel, $User) {
		$this->findPlugin('seen')->saveVar($User->id, array(
			'action' => 'JOIN',
			'server' => $this->Server->host,
			'channel'=> $Channel->name,
			'nick'   => $User->nick,
			'time'   => $this->Bot->time
		));
	}
	
}

?>
