<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\Network\SimpleHTTP;

class HappyHardcorePlugin extends Plugin {
	// TODO: broken
	public $enabledByDefault = false;
	public $hideFromHelp = true;

	public $triggers = array('!hc', '!hhc', '!happyhardcore', '!happy-hardcore');
	
	public $helpTriggers = array('!hc');
	public $helpText = 'Prints which track is currently running on di.fm\'s hardcore radio.';
	public $helpCategory = 'Internet';
	
	function isTriggered() {
        $data = $this->getHappyHardcoreStats();
        
        $this->reply(sprintf("Now playing on http://www.happyhardcore.com %s (%s) - %s listeners",
			$data['artist'].' - '.$data['title'],
			$data['is_live'] ? 'Live Show' : $this->trackTime($data['progress']).'/'.$this->trackTime($data['length']),
			$data['listeners']
		));
	}
	
	private function trackTime($seconds) {
		$minutes = (int)($seconds/60);
		$seconds_remain = $seconds % ($minutes*60);
	return $minutes.':'.str_pad($seconds_remain, 2, '0', STR_PAD_LEFT);
	}
	
	private function getHappyHardcoreStats() {
		$html = SimpleHTTP::GET('http://www.happyhardcore.com/radio/player/tools/timequery.asp');
		preg_match_all('/station\.(.+?)\s*=\s*\'?(.*?)\'?\s*;/', $html, $arr);
		
		$raw_data = array();
		for($x=0;$x<sizeof($arr[1]);$x++) {
			$raw_data[$arr[1][$x]] = $arr[2][$x];
		}
		
		$data = array(
			'is_live' => $raw_data['IsLive'] === 'false' ? false : true,
			'artist' => str_replace('\\', '', $raw_data['TrackArtist']),
			'title' => str_replace('\\', '', $raw_data['TrackTitle']),
			'length' => $raw_data['TrackLength'],
			'progress' => $raw_data['TrackLength']-$raw_data['TrackRemain'],
			'listeners' => $raw_data['ListenerCount']
		);
		
	return $data;
	}
	
}

?>
