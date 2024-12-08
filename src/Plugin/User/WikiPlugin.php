<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\Network\SimpleHTTP;

class WikiPlugin extends Plugin {
	// TODO: broken
	public $enabledByDefault = false;
	public $hideFromHelp = true;

	public $triggers = array('!wiki', '!wikipedia', '!wiki-en', '!wiki-de', '!stupi');
	public $usage = '<term>';
	
	public $helpCategory = 'Internet';
	public $helpTriggers = array('!wiki', '!stupi');
	public $helpText = "Fetches information from wikipedia or stupidedia for the given <term> in the channel/user language.";
	
	protected $config = array(
		'language' => array(
			'type' => 'enum',
			'options' => array('de', 'en'),
			'default' => 'en',
			'description' => 'Determines the language to use with the !wiki command.'
		)
	);
	
	private $maxlength = 433;
	private $notfoundText = 'Your term doesn\'t exist at this Wikipedia Database.';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply("Usage: ".$this->data['trigger']." term");
			return;
		}

		$term = $this->data['text'];

		switch($this->data['trigger']) {
			case "!wiki":
			case "!wikipedia":
				switch($this->getConfig('language')) {
					case 'de':
						$output = $this->getWikiText($term, 'de.wikipedia.org', '/wiki/', 'Diese Seite existiert nicht');
						if($output !== false) break;
					case 'en':
						$output = $this->getWikiText($term, 'en.wikipedia.org', '/wiki/', 'Wikipedia does not have an article with this exact name');
					break;
				}
				break;
			case '!wiki-de':
				$output = $this->getWikiText($term, 'de.wikipedia.org', '/wiki/', 'Diese Seite existiert nicht');
				break;
			case "!wiki-en":
				$output = $this->getWikiText($term, 'en.wikipedia.org', '/wiki/', 'Wikipedia does not have an article with this exact name');
				break;
			case "!stupi":
				$output = $this->getWikiText($term, 'www.stupidedia.org', '/stupi/', 'Der Artikel kann nicht angezeigt werden');
				break;
		}
		
		if(!$output) {
			$this->reply($this->notfoundText);
			return;
		}


		$link = $output['link'];
		$text = substr($output['text'],0,$this->maxlength - (strlen($link)+8));
		$text.= "... ( ".$link." )";

		$this->reply($text);
	}
	
	function getWikiText($term, $server, $path, $notfound) {
		$term = str_replace(" ","_",$term);
		$term[0] = strtoupper($term[0]);
		$html = SimpleHTTP::GET('http://'.$server.$path.str_replace("%23","#",urlencode($term)));
		$content = str_replace(array("\r", "\n"), ' ', $html);
		while(strstr($content, '  ')) $content = str_replace('  ', ' ', $content);

		if(stristr($content,$notfound)) {
			return false;
		}

		$pos = strpos($content,'<div id="contentSub">');
		$content = substr($content,$pos);
		$content = preg_replace("#<tr.*?</tr>#",'',$content);
		$content = str_replace("</li>",",</li>",$content);

		preg_match_all("#<(p|li)>(.*?)</(p|li)>#",$content,$arr);

		$content = "";
		foreach($arr[2] as $row) {
			$row = trim(strip_tags($row));
			if(empty($row)) continue;
			$content.= $row." ";
		}

		$content = html_entity_decode($content);
		$content = str_replace(chr(160)," ",$content);

		$output['text'] = $content;
		$output['link'] = "http://".$server.$path.urlencode($term);
		return $output;
	}

}

?>
