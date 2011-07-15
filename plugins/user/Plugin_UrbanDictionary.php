<?php

class Plugin_UrbanDictionary extends Plugin {
	
	public $triggers = array('!define', '!wtf', '!urban', '!ud', '!urban-dictionary');
	private $usage = 'Usage: %s <term>';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply(sprintf($this->usage, $this->data['trigger']));
			return;
		}
		
		$term = $this->data['text'];
		
		$res = libHTTP::GET('www.urbandictionary.com', '/define.php?term='.urlencode($term), null, 2);
		if(!$res) {
			$this->reply('Timeout on contacting urbandictionary.com');
			return;
		}
		
		if(strstr($res['raw'], "isn't defined <a href")) {
			$this->reply($term.' has no definition. Feel free to add one at http://www.urbandictionary.com/add.php?word='.$term);
			return;
		}
		
		preg_match('#<div class="definition">(.+?)</div>.*?<div class="example">(.+?)</div>#s', $res['raw'], $arr);
		
		$definition = trim(html_entity_decode(strip_tags($arr[1])));
		$example    = trim(html_entity_decode(strip_tags($arr[2])));
		
		$definition = strtr($definition, array("\r" => ' ', "\n" => ' '));
		while(false !== strstr($definition, '  ')) $definition = str_replace('  ', ' ', $definition);
		
		$example = strtr($example, array("\r" => ' | ', "\n" => ' | '));
		while(false !== strstr($example, ' |  | ')) $example = str_replace(' |  | ', ' | ', $example);
		while(false !== strstr($example, '  '))     $example = str_replace('  ', ' ', $example);
		
		$this->reply($definition);
		$this->reply("\x02Example:\x02 ".$example);
		
		
	}
}

?>
