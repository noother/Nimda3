<?php

class Plugin_ChannelPeak extends Plugin {
	
	protected $triggers = array('!peak');
	
	function isTriggered() {
		if($this->data['isQuery']) {
			$this->reply('This command only works in a channel.');
			return;
		}
		
		$peak = $this->Channel->data['peak'];
		
		$this->reply(sprintf(
			"Current channel peak for \x02%s\x02: %d users online at %s (%s ago)",
				$this->Channel->name,
				$peak['peak'],
				$peak['date'],
				libTime::secondsToString(libTime::getSecondsDifference(date('r'), $peak['date']))
		));
	}
	
	function onJoin() {
		$user_count = sizeof($this->Channel->users);
		
		if($user_count > $this->Channel->data['peak']['peak']) {
			$old_peak = $this->Channel->data['peak'];
			
			$this->MySQL->query("UPDATE `server_channelpeaks` SET `peak` = ".$user_count.", `date` = NOW() WHERE server_id = ".$this->Server->id." AND channel = '".addslashes($this->Channel->name)."'");
			$this->Channel->data['peak'] = array('peak' => $user_count, 'date' => date('Y-m-d H:i:s'));
			
			$this->reply(sprintf(
				"New channel peak for \x02%s\x02: %d users online. Old one was %d users online at %s (%s ago)",
					$this->Channel->name,
					$user_count,
					$old_peak['peak'],
					$old_peak['date'],
					libTime::secondsToString(libTime::getSecondsDifference(date('r'), $old_peak['date']))
			));
		}
	}
	
	function onMeJoin() {
		$peak = $this->MySQL->fetchRow("SELECT `peak`, `date` FROM `server_channelpeaks` WHERE `server_id` = ".$this->Server->id." AND `channel` = '".addslashes($this->Channel->name)."'");
		
		if(!$peak) {
			$user_count = sizeof($this->Channel->users);
			
			$this->MySQL->query("INSERT INTO `server_channelpeaks` (`server_id`, `channel`, `peak`, `date`) VALUES (
				".$this->Server->id.",
				'".addslashes($this->Channel->name)."',
				".$user_count.",
				NOW()
			)");
			
			$this->Channel->data['peak'] = array('peak' => $user_count, 'date' => date('Y-m-d H:i:s'));
		} else {
			$this->Channel->data['peak'] = $peak;
		}
	}
	
}

?>
