<?php

class Plugin_ChannelPeak extends Plugin {
	
	public $triggers = array('!peak');
	
	protected $config = array(
		'peakshow' => array(
			'type' => 'enum',
			'options' => array('yes', 'no'),
			'default' => 'no',
			'description' => 'Determines wherever or not new channel peaks should be announced in the channel'
		)
	);
	
	function isTriggered() {
		if($this->data['isQuery']) {
			$this->reply('This command only works in a channel.');
			return;
		}
		
		$this->reply(sprintf(
			"Current channel peak for \x02%s\x02: %d users online at %s (%s ago)",
				$this->Channel->name,
				$this->Channel->getVar('peak'),
				$this->Channel->getVar('peak_date'),
				libTime::secondsToString(libTime::getSecondsDifference(date('r'), $this->Channel->getVar('peak_date')))
		));
	}
	
	function onJoin() {
		$user_count = sizeof($this->Channel->users);
		
		if($user_count > $this->Channel->getVar('peak')) {
			if($this->getConfig('peakshow') === 'yes') {
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
		}
	}
	
}

?>
