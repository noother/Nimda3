<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\Network\SimpleHTTP;

class UrbanDictionaryPlugin extends Plugin {
	// TODO: broken
	public $enabledByDefault = false;
	public $hideFromHelp = true;

	public $triggers = array('!wtf', '!define', '!urban', '!ud', '!urban-dictionary');
	public $usage = '<term>';
	
	public $helpCategory = 'Internet';
	public $helpTriggers = array('!define');
	public $helpText = "Fetches definition and example from urbandictionary.org and prints it back.";
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}
		
		$term = $this->data['text'];
		$this->addJob('getDefinition', $term);
		
	}
	
	function onJobDone() {
		$data = &$this->data['result'];
		if(isset($data['timeout'])) {
			$this->reply('Timeout on contacting urbandictionary.com');
			return;
		}
		
		if(isset($data['undefined'])) {
			$this->reply($data['term'].' has no definition. Feel free to add one at http://www.urbandictionary.com/add.php?word='.urlencode($data['term']));
			return;
		}
		
		$this->reply($data['definition']);
		if(isset($data['example'])) {
			$this->reply("\x02Example:\x02 ".$data['example']);
		}
	}
	
	public function getDefinition($term) {
		$data = array('term' => $term);
		
		$html = SimpleHTTP::GET('http://www.urbandictionary.com/define.php?term='.urlencode($term));
		if($html === false) {
			$data['timeout'] = true;
			return $data;
		}
		
		if(strstr($html, "isn't defined.<br/>")) {
			$data['undefined'] = true;
			return $data;
		}
		
		preg_match('#<div class=\'meaning\'>(.+?)</div>.*?<div class=\'example\'>(.*?)</div>#s', $html, $arr);
		
		$definition = trim(html_entity_decode(strip_tags(br2nl($arr[1]))));
		$definition = strtr($definition, array("\r" => ' ', "\n" => ' '));
		$definition = preg_replace_callback('/&#([a-z0-9]+);/i', create_function('$a', 'return chr(hexdec($a[0]));'), $definition);
		while(false !== strstr($definition, '  ')) $definition = str_replace('  ', ' ', $definition);
		if(strlen($definition) > 800) $definition = substr($definition, 0 ,800).'...';
		$data['definition'] = $definition;
		
		if(!empty($arr[2])) {
			$example = trim(html_entity_decode(strip_tags(br2nl($arr[2]))));
			$example = strtr($example, array("\r" => ' | ', "\n" => ' | '));
			$example = preg_replace_callback('/&#([a-z0-9]+);/i', create_function('$a', 'return chr(hexdec($a[0]));'), $example);
			while(false !== strstr($example, ' |  | ')) $example = str_replace(' |  | ', ' | ', $example);
			while(false !== strstr($example, '  '))     $example = str_replace('  ', ' ', $example);
			if(strlen($example) > 800) $example = substr($example, 0, 800).'...';
			$data['example'] = $example;
		}
		
	return $data;
	}
}

?>
