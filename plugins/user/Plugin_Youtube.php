<?php

class Plugin_Youtube extends Plugin {
	/*
	protected $config = array(
		'active' => array(
			'type' => 'bool',
			'default' => true,
			'description' => 'If activated, information about Youtube-Links will be fetched and displayed in the channel'
		)
	);
	
	public $triggers = array('!youtube');
	*/
	
	function onChannelMessage() {
		//if(!$this->getConfig('active')) return;
		
		if(false === $id = libInternet::youtubeID($this->data['text'])) return;
		
		$data = libInternet::getYoutubeData($id);
		
		
		$this->reply(sprintf(
			"\x02[YouTube]\x02 \x02Title:\x02 %s | \x02Rating:\x02 %.2f/5.00 | \x02Views:\x02 %s",
				$data['title'],
				$data['rating'],
				number_format($data['views'])
		));
		
	}
	
}

?>
