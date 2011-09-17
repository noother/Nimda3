<?php

class Plugin_Movie extends Plugin {
	
	public $triggers = array('!movie', '!tmdb', '!film');
	
	protected $config = array(
		'language' => array(
			'type' => 'enum',
			'options' => array('de', 'en'),
			'default' => 'en',
			'description' => 'Movie description will get displayed in this language'
		)
	);
	
	private $api_key  = '9fc8c3894a459cac8c75e3284b712dfc'; // shamelessly stolen from gcstar
	private $usage    = 'Usage: %s <movie>';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply(sprintf($this->usage, $this->data['trigger']));
			return;
		}
		
		$res = libHTTP::GET('api.themoviedb.org', '/2.1/Movie.search/'.$this->getConfig('language').'/xml/'.$this->api_key.'/'.urlencode($this->data['text']));
		$XML = new SimpleXMLElement($res['raw']);
		$tmp = $XML->xpath('opensearch:totalResults');
		$results = (int)$tmp[0];
		if(!$results) {
			if($this->getConfig('language') != 'en') {
				$res = libHTTP::GET('api.themoviedb.org', '/2.1/Movie.search/en/xml/'.$this->api_key.'/'.urlencode($this->data['text']));
				$XML = new SimpleXMLElement($res['raw']);
				$tmp = $XML->xpath('opensearch:totalResults');
				$results = (int)$tmp[0];
			}
		}
		
		if(!$results) {
			$this->reply('There is no information available about this movie.');
			return;
		}
		
		$tmp = $XML->children()->children();
		$Movie = $tmp[0];
		
		$text = "\x02".$Movie->name."\x02";
		if((string)$Movie->original_name != (string)$Movie->name) $text.= ' ('.$Movie->original_name.')';
		if(!empty($Movie->released))              $text.= " | \x02Released:\x02 ".$Movie->released;
		if($Movie->rating != '0.0')               $text.= " | \x02Press Rating:\x02 ".$Movie->rating.'/10';
		if(!empty($Movie->certification))         $text.= " | \x02Rated:\x02 ".$Movie->certification;
		$text.= ' ('.$Movie->url.')';
		
		$this->reply($text);
		$this->reply($Movie->overview);
	}
	
}

?>
