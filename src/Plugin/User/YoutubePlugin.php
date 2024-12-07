<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\Library\Internet;

class YoutubePlugin extends Plugin {
	
	public $helpCategory = 'Internet';
	public $helpText = "Observes the channel for youtube links and prints back information about it if found.";
	
	function onChannelMessage() {
		if(false === $id = Internet::youtubeID($this->data['text'])) return;
		if(false === $data = Internet::getYoutubeData($id)) return;
		
		$this->reply(sprintf(
			"\x02[YouTube]\x02 \x02Title:\x02 %s | \x02Rating:\x02 %.2f/5.00 | \x02Views:\x02 %s",
				$data['title'],
				$data['rating'],
				number_format($data['views'])
		));
		
	}
	
}

?>
