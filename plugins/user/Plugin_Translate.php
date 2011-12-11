<?php

class Plugin_Translate extends Plugin {
	
	protected $config = array(
		'language' => array(
			'type' => 'enum',
			'options' => array(),
			'default' => 'en',
			'description' => 'Determines to which language !translate should auto-translate'
		)
	);
	
	public $triggers = array('!translate');
	
	public $helpTriggers = array('!translate');
	public $helpCategory = 'Internet';
	public $helpText = "Translates some foreign language to the channel language. There are also shortcuts available, like !en-de, !pl-ja, etc.";
	public $usage = '<text>';
	
	
	function onLoad() {
		$languages = array('af', 'sq', 'ar', 'hy', 'az', 'eu', 'bn', 'bg', 'da', 'de', 'en', 'et', 'fi', 'fr', 'gl', 'ka', 'el', 'gu', 'ht', 'iw', 'hi', 'id', 'ga', 'is', 'it', 'ja', 'yi', 'kn', 'ca', 'ko', 'hr', 'la', 'lv', 'lt', 'ms', 'mt', 'mk', 'nl', 'no', 'fa', 'pl', 'pt', 'ro', 'ru', 'sv', 'sr', 'sk', 'sl', 'es', 'sw', 'tl', 'ta', 'te', 'th', 'cs', 'tr', 'uk', 'hu', 'ur', 'vi', 'cy', 'be');
		
		foreach($languages as $lang1) {
			foreach($languages as $lang2) {
				if($lang1 == $lang2) continue;
				$this->triggers[] = '!'.$lang1.'-'.$lang2;
			}
			$this->triggers[] = '!auto-'.$lang1;
			$this->config['language']['options'][] = $lang1;
		}
	}
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}
		
		switch($this->data['trigger']) {
			case '!translate':
				$sl = 'auto';
				$tl = $this->getConfig('language');
			break;
			default:
				$trigger = substr($this->data['trigger'], 1);
				list($sl, $tl) = explode('-', $trigger);
			break;
		}
		
		if(false === $translation = libInternet::googleTranslate($this->data['text'], $sl, $tl, $sl=='auto'?true:false)) {
			$this->reply("\x02Something weird occurred \x02");
			return;
		}
		
		if($sl == 'auto') {
			$this->reply("\x02Translation from ".$translation['source_lang'].": \x02".$translation['translation']);
		} else {
			$this->reply("\x02Translation: \x02".$translation);
		}
	}
	
}

?>
