<?php

class Plugin_Interpreter extends Plugin {
	
	public $triggers = array('!interpreter');
	
	public $helpTriggers = array('!interpreter');
	public $usage = '[language]|stop';
	public $helpText = 'Translates everything said into the given language or the channels default language.';
	
	private $state = array();
	private $languages = array('af', 'ar', 'az', 'be', 'bg', 'bn', 'ca', 'cs', 'cy', 'da', 'de', 'el', 'en', 'es', 'et', 'eu', 'fa', 'fi', 'fr', 'ga', 'gl', 'gu', 'hi', 'hr', 'ht', 'hu', 'hy', 'id', 'is', 'it', 'iw', 'ja', 'ka', 'kn', 'ko', 'la', 'lt', 'lv', 'mk', 'ms', 'mt', 'nl', 'no', 'pl', 'pt', 'ro', 'ru', 'sk', 'sl', 'sq', 'sr', 'sv', 'sw', 'ta', 'te', 'th', 'tl', 'tr', 'uk', 'ur', 'vi', 'yi');
	private $toLang = 'en';
	
	function isTriggered() {
		if(!isset($this->data['text'])) $lang = 'en';
		else $lang = $this->data['text'];
		
		switch($lang) {
			case 'stop':
				unset($this->state[$this->Server->id.':'.$this->Channel->id]);
				$this->isRunning = false;
			break;
			default:
				if(!in_array($lang, $this->languages)) {
					$this->reply('This language is not available.');
					return;
				}
				
				$this->state[$this->Server->id.':'.$this->Channel->id] = true;
				$this->toLang = $lang;
				$this->reply('Started translating to '.$lang);
			break;
		}
	}
	
	function onChannelMessage() {
		if(!isset($this->state[$this->Server->id.':'.$this->Channel->id])) return;
		
		$translation = libInternet::googleTranslate($this->data['text'], 'auto', $this->toLang);
		if(empty($translation)) return;
		
		if(strtolower($translation) == strtolower($this->data['text'])) return;
		
		$this->reply('<'.libIRC::noHighlight($this->User->nick).'> '.$translation);
	}
	
}

?>
