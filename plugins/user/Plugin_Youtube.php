<?php

class Plugin_Youtube extends Plugin {
	
	public $triggers = array('!youtube');
	
	function onChannelMessage() {
		if(false === $id = libInternet::youtubeID($this->data['text'])) return;
		if(false === $data = libInternet::getYoutubeData($id)) return;
		
		$this->reply(sprintf(
			"\x02[YouTube]\x02 \x02Title:\x02 %s | \x02Rating:\x02 %.2f/5.00 | \x02Views:\x02 %s",
				$data['title'],
				$data['rating'],
				number_format($data['views'])
		));
		
	}
	
}

?>
