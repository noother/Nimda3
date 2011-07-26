<?php

class Plugin_HappyHardcore extends Plugin {
	
	public $triggers = array('!np', '!hc', '!happyhardcore', '!happy-hardcore');
	
	function isTriggered() {
        $data = $this->getHappyHardcoreStats();
        
        $this->reply(sprintf("Now playing on http://www.happyhardcore.com: %s (%s) - %d listeners",
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
		$res = libHTTP::GET('www.happyhardcore.com', '/radio/player/tools/timequery.asp');
		preg_match_all('/station\.(.+?)\s*=\s*\'?(.*?)\'?\s*;/', $res['raw'], $arr);
		
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
