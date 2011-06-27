<?php

class Plugin_Wiki extends Plugin {
	
	public $triggers = array('!wiki', '!wikipedia', '!wiki-en', '!stupi');
	private $wikiServer = 'de.wikipedia.org';
	private $wikiAltServer = 'en.wikipedia.org';
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
				$output = $this->getWikiText($term,$this->wikiServer,"/wiki/","Diese Seite existiert nicht");
				if(!$output) {
					$output = $this->getWikiText($term,$this->wikiAltServer,"/wiki/","Wikipedia does not have an article with this exact name");
				}
				break;
			case "!wiki-en":
				$output = $this->getWikiText($term,$this->wikiAltServer,"/wiki/","Wikipedia does not have an article with this exact name");
				break;
			case "!stupi":
				$output = $this->getWikiText($term,"www.stupidedia.org","/stupi/","Der Artikel kann nicht angezeigt werden");
				break;
		}

		if(!$output) {
			$this->reply($this->notfoundText);
			return;
		}


		$link = $output['link'];
		$text = substr($output['text'],0,$this->maxlength - (strlen($link)+6));
		$text.= "... (".$link.")";

		$this->reply($text);
	}
	
	function getWikiText($term, $server, $path, $notfound) {
		$term = str_replace(" ","_",$term);
		$term[0] = strtoupper($term[0]);
		$result = libHTTP::GET($server,$path.str_replace("%23","#",urlencode($term)));
		$header = $result['header'];

		/*if(isset($header['Location'])) {
			preg_match("#".$path."(.*)#",$header['Location'],$arr);
			return $this->getWikiText(urldecode($arr[1]),$server,$path,$notfound);
		}*/

		$content = implode(" ",$result['content']);

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
			var_dump($content);
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
