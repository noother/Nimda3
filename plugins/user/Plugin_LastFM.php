<?php

class Plugin_LastFM extends Plugin {
	
	public $triggers = array('!lastfm', '!lfm');
	public $usage = '<nick>';
	
	public $helpCategory = 'Internet';
	public $helpTriggers = array('!lastfm');
	public $helpText = 'Displays currently playing track on lastfm.';

	// if you set this API key to an last.fm API key, we will fetch real "now playing" instead of last scrobbled track
	private $apiKey = '';
	


	function isTriggered() {
		$username  = isset($this->data['text']) ? $this->data['text'] : $this->User->nick;

		$track = $this->getRecentTracks($username);

		if ($track === false) {
			$this->reply('User does not exist on last.fm');
			return;
		}

		if ($track['nowplaying'])
			$this->reply($username . " np: \x02" . $track['title'] . "\x02");
		else
			$this->reply($username . " last played: \x02" . $track['title'] . "\x02");
	}

	
	function getRecentTracks($nick) {
		
		if ($this->apiKey != '') {
			$ret = libHTTP::GET('http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks'
				.'&user='.urlencode($nick)
				.'&api_key='.$this->apiKey);
			
			$xml = simplexml_load_string($ret);
			
			if (!$xml) {
				// this should be an error code if it isnt xml
				echo 'last.fm response: '.$ret;
				return false;
			}

			$res['title'] = $xml->recenttracks->track[0]->artist . ' - ' . $xml->recenttracks->track[0]->name;
			$res['nowplaying'] = $xml->recenttracks->track[0]['nowplaying'] == 'true';

			return $res;
		} else {

			$ret = libHTTP::GET('http://ws.audioscrobbler.com/2.0/user/' . urlencode($nick) . '/recenttracks.rss');
			$xml = simplexml_load_string($ret);
			
			if (!$xml)
				return false;

			return array('title' => $xml->channel->item[0]->title, 'nowplaying' => false);
		}
	}

}

?>
