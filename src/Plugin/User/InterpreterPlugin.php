<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\Library\Internet;
use noother\Library\IRC;

class InterpreterPlugin extends Plugin {
	// TODO: broken
	public $enabledByDefault = false;
	public $hideFromHelp = true;

	public $triggers = array('!interpreter');
	
	public $helpTriggers = array('!interpreter');
	public $usage = '(language1 language2)|stop';
	public $helpText = 'Acts as an Interpreter between 2 languages.';
	public $helpCategory = 'Internet';
	
	private $interpreters = array();
	private $languages = array('af', 'ar', 'az', 'be', 'bg', 'bn', 'ca', 'cs', 'cy', 'da', 'de', 'el', 'en', 'es', 'et', 'eu', 'fa', 'fi', 'fr', 'ga', 'gl', 'gu', 'hi', 'hr', 'ht', 'hu', 'hy', 'id', 'is', 'it', 'iw', 'ja', 'ka', 'kn', 'ko', 'la', 'lt', 'lv', 'mk', 'ms', 'mt', 'nl', 'no', 'pl', 'pt', 'ro', 'ru', 'sk', 'sl', 'sq', 'sr', 'sv', 'sw', 'ta', 'te', 'th', 'tl', 'tr', 'uk', 'ur', 'vi', 'yi');
	
	function isTriggered() {
		if(!isset($this->data['text'])) $lang = 'en';
		else $lang = $this->data['text'];
		
		switch($lang) {
			case 'stop':
			case 'off':
				unset($this->interpreters[$this->Server->id.':'.$this->Channel->id]);
				$this->isRunning = false;
				$this->reply('Interpreting stopped.');
			break;
			default:
				$langs = explode(' ', $this->data['text'], 2);
				if(!in_array($langs[0], $this->languages) || !in_array($langs[1], $this->languages)) {
					$this->reply('A language is not available. Available languages are '.implode(', ', $this->languages));
					return;
				}
				
				$this->interpreters[$this->Server->id.':'.$this->Channel->id] = $langs;
				$this->reply('Started translating between '.$langs[0].' & '.$langs[1]);
			break;
		}
	}
	
	function onChannelMessage() {
		if(!isset($this->interpreters[$this->Server->id.':'.$this->Channel->id])) return;
		
		foreach($this->interpreters[$this->Server->id.':'.$this->Channel->id] as $lang) {
			$translation = Internet::googleTranslate($this->data['text'], 'auto', $lang);
			if(empty($translation)) continue;
			
			if(strtolower($translation) == strtolower($this->data['text'])) continue;;
			
			$this->reply('<'.IRC::noHighlight($this->User->nick).'> '.$translation);
		}
	}
	
}

?>
