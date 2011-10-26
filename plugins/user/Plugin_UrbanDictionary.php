<?php

class Plugin_UrbanDictionary extends Plugin {
	
	public $triggers = array('!wtf', '!define', '!urban', '!ud', '!urban-dictionary');
	private $usage = 'Usage: %s <term>';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply(sprintf($this->usage, $this->data['trigger']));
			return;
		}
		
		$term = $this->data['text'];
		
		$html = libHTTP::GET('http://www.urbandictionary.com/define.php?term='.urlencode($term));
		if($html === false) {
			$this->reply('Timeout on contacting urbandictionary.com');
			return;
		}
		
		if(strstr($html, "isn't defined <a href")) {
			$this->reply($term.' has no definition. Feel free to add one at http://www.urbandictionary.com/add.php?word='.$term);
			return;
		}
		
		preg_match('#<div class="definition">(.+?)</div>.*?<div class="example">(.*?)</div>#s', $html, $arr);
		
		$definition = trim(html_entity_decode(strip_tags(br2nl($arr[1]))));
		$definition = strtr($definition, array("\r" => ' ', "\n" => ' '));
		while(false !== strstr($definition, '  ')) $definition = str_replace('  ', ' ', $definition);
		if(strlen($definition) > 800) $definition = substr($definition, 0 ,800).'...';
		$this->reply($definition);
		
		if(!empty($arr[2])) {
			$example = trim(html_entity_decode(strip_tags(br2nl($arr[2]))));
			$example = strtr($example, array("\r" => ' | ', "\n" => ' | '));
			while(false !== strstr($example, ' |  | ')) $example = str_replace(' |  | ', ' | ', $example);
			while(false !== strstr($example, '  '))     $example = str_replace('  ', ' ', $example);
			if(strlen($example) > 800) $example = substr($example, 0, 800).'...';
			$this->reply("\x02Example:\x02 ".$example);
		}
	}
}

?>
