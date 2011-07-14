<?php

class Plugin_ChannelPeak extends Plugin {
	
	public $triggers = array('!peak', '!peakshow');
	
	private $usage = 'Usage: !peakshow <enable|disable>';
	
	function isTriggered() {
		if($this->data['isQuery']) {
			$this->reply('This command only works in a channel.');
			return;
		}
		
		switch($this->data['trigger']) {
			case '!peak':
				$this->reply(sprintf(
					"Current channel peak for \x02%s\x02: %d users online at %s (%s ago)",
						$this->Channel->name,
						$this->Channel->getVar('peak'),
						$this->Channel->getVar('peak_date'),
						libTime::secondsToString(libTime::getSecondsDifference(date('r'), $this->Channel->getVar('peak_date')))
				));
			break;
			case '!peakshow':
				if(!isset($this->data['text'])) {
					$this->reply(sprintf('The peakshow for %s on %s is %s.',
						$this->Channel->name,
						$this->Server->host,
						$this->Channel->getVar('peakshow_enabled') ? 'enabled' : 'disabled'
					));
					return;
				}
				
				switch($this->data['text']) {
					case 'enable':
						if($this->User->mode != '@') {
							$this->reply('You have to be an operator in this channel to enable the peakshow.');
							return;
						}
						
						if($this->Channel->getVar('peakshow_enabled')) {
							$this->reply('The peakshow is already enabled.');
							return;
						}
					
						$this->Channel->saveVar('peakshow_enabled', true);
						$this->reply(sprintf('The peakshow for %s on %s has been enabled',
							$this->Channel->name,
							$this->Server->id
						));
					break;
					case 'disable':
						if($this->User->mode != '@') {
							$this->reply('You have to be an operator in this channel to disable the peakshow.');
							return;
						}
						
						if(!$this->Channel->getVar('peakshow_enabled')) {
							$this->reply('The peakshow is already disabled.');
							return;
						}
					
						$this->Channel->saveVar('peakshow_enabled', false);
						$this->reply(sprintf('The peakshow for %s on %s has been disabled',
							$this->Channel->name,
							$this->Server->id
						));
					break;
					default:
						$this->reply($this->usage);
					break;
				}
			break;
		}
		
		

	}
	
	function onJoin() {
		$user_count = sizeof($this->Channel->users);
		
		if($user_count > $this->Channel->getVar('peak')) {
			if($this->Channel->getVar('peakshow_enabled')) {
				$this->reply(sprintf(
					"New channel peak for \x02%s\x02: %d users online. Old one was %d users online at %s (%s ago)",
						$this->Channel->name,
						$user_count,
						$this->Channel->getVar('peak'),
						$this->Channel->getVar('peak_date'),
						libTime::secondsToString(libTime::getSecondsDifference(date('r'), $this->Channel->getVar('peak_date')))
				));
			}
			
			$this->Channel->saveVar('peak', $user_count);
			$this->Channel->saveVar('peak_date', date('Y-m-d H:i:s'));
		}
	}
	
	function onMeJoin() {
		if(!$this->Channel->getVar('peak')) {
			$user_count = sizeof($this->Channel->users);
			$this->Channel->saveVar('peak', $user_count);
			$this->Channel->saveVar('peak_date', date('Y-m-d H:i:s'));
			$this->Channel->saveVar('peakshow_enabled', false);
		}
	}
	
}

?>
