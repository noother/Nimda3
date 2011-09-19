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
		
		if(!preg_match("#(www\.)?youtube\.com/.*?(\?|&)v=([a-zA-Z0-9_-]{11})#", $this->data['text'], $videoIdArray)) {
			return;
		}
		$videoId = $videoIdArray[3];
		
		if (!$this->validYoutubeId($videoId)) return;
		
		$res = libHTTP::GET('gdata.youtube.com','/feeds/api/videos/'.$videoId);
		$file = $res['raw'];
		
		$file = utf8_encode($file);
		$xml = simplexml_load_string($file);
		if(!$xml) return;
		
		$xml_rates = $xml->children('http://schemas.google.com/g/2005');
		$xml_views = $xml->children('http://gdata.youtube.com/schemas/2007');
		
		$avgRating = number_format((float)$xml->children('http://schemas.google.com/g/2005')->rating->attributes()->average, 2);
		$views = number_format((int)$xml->children('http://gdata.youtube.com/schemas/2007')->statistics->attributes()->viewCount);
		$title = utf8_decode($xml->title);
		
		$this->reply("\x02[YouTube]\x02 |\x02 Title: \x02".$title. "\x02 \x02|\x02 Rate: \x02". + $avgRating."/5.00\x02 \x02|\x02 Views: \x02".$views);
		
	}
	
	function validYoutubeId($id) {
		if ($id == "") return false;
		
		$res = libHTTP::GET('gdata.youtube.com','/feeds/api/videos/'.$id);
		$data = $res['raw'];
		if (!$data) return false;
		if ($data == "Invalid id") return false;
		
		return true;
	}
	
}

?>
