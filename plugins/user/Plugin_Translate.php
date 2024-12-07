<?php

use noother\Library\Internet;

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
		$languages = array('af', 'ar', 'az', 'be', 'bg', 'bn', 'ca', 'cs', 'cy', 'da', 'de', 'el', 'en', 'es', 'et', 'eu', 'fa', 'fi', 'fr', 'ga', 'gl', 'gu', 'hi', 'hr', 'ht', 'hu', 'hy', 'id', 'is', 'it', 'iw', 'ja', 'ka', 'kn', 'ko', 'la', 'lt', 'lv', 'mk', 'ms', 'mt', 'nl', 'no', 'pl', 'pt', 'ro', 'ru', 'sk', 'sl', 'sq', 'sr', 'sv', 'sw', 'ta', 'te', 'th', 'tl', 'tr', 'uk', 'ur', 'vi', 'yi');
		
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
		
		if(false === $translation = Internet::googleTranslate($this->data['text'], $sl, $tl, $sl=='auto'?true:false)) {
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
