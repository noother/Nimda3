<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\Network\SimpleHTTP;

class LastFMPlugin extends Plugin {
	// TODO: broken
	public $enabledByDefault = false;
	public $hideFromHelp = true;

	public $triggers = array('!np', '!lastfm', '!lfm');
	public $usage = '[nick]';
	
	public $helpCategory = 'Internet';
	public $helpTriggers = array('!np');
	public $helpText = 'Displays currently playing track on lastfm.';

	// if you set this API key to an last.fm API key, we will fetch real "now playing" instead of last scrobbled track
	private $apiKey = false;
	


	function isTriggered() {
		$username  = isset($this->data['text']) ? $this->data['text'] : $this->User->nick;

		$track = $this->getRecentTracks($username);

		if ($track === false) {
			$this->reply('User does not exist on last.fm');
			return;
		}

		if (isset($track['notracks'])) {
			$this->reply($username . ' has never heard any music');
			return;
		}

		if ($track['nowplaying'])
			$this->reply($username . " is now playing: \x02" . $track['title'] . "\x02");
		else
			$this->reply($username . " last played: \x02" . $track['title'] . "\x02");
	}

	
	function getRecentTracks($nick) {
		
		if ($this->apiKey !== false) {
			$ret = SimpleHTTP::GET('http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks'
				.'&user='.urlencode($nick)
				.'&api_key='.$this->apiKey);
			
			$xml = simplexml_load_string($ret);
			
			if (!$xml || !empty($xml->error[0]))
				return false;

			if (intval($xml->recenttracks['total']) == 0)
				return array('notracks' => true);

			$res['title'] = $xml->recenttracks->track[0]->artist . ' - ' . $xml->recenttracks->track[0]->name;
			$res['nowplaying'] = $xml->recenttracks->track[0]['nowplaying'] == 'true';

			return $res;
		} else {

			$ret = SimpleHTTP::GET('http://ws.audioscrobbler.com/2.0/user/' . urlencode($nick) . '/recenttracks.rss');
			$xml = simplexml_load_string($ret);
			
			if (!$xml)
				return false;
			
			if (empty($xml->channel->item))
				return array('notracks' => true);

			return array('title' => $xml->channel->item[0]->title, 'nowplaying' => false);
		}
	}

}

?>
