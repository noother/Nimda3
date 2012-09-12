<?php

class libString {
	
	static function isUTF8($string) {
		return (utf8_encode(utf8_decode($string)) == $string);
	}
	
	static function capitalize($string) {
		return ucfirst(strtolower($string));
	}
	
	static function convertUmlaute($string) {
		$replace = array("ä" => "ae", "ö" => "oe", "ü" => "ue", "Ä" => "Ae", "Ö" => "Oe", "Ü" => "Ue", 'ß' => 'ss');
		return strtr($string,$replace);
	}
	
	static function countSmilies($string) {
		$count = 0;
		$smilies = array(	":)",":-)",":-]",":]",":(",":-(",":<",":-<",":>",":->",":[",":-[",
							":/",":-/",":|",":-|",";)",";-)",":p",":P",":-p",":-P",";p",";-P",":D",
							":-D",";D",";-D",":ß",":-ß",";ß",";-ß","B)","B-)","8)","8]",":o",":O",
							":-o",":-O",";o",";O",";-o",";-O",":S",":s",":-S",":-8",":-B",":8",":B",
							":x",":-x",";(",";-(",":'(",":'-(",":_(",":o)",";o)",">:-)",">:)","0:-)",
							"0:)","xD","XD","D:",":3",":V",":-t",":t",":*)",":-)*",":)*",":^o",
							":&",":-&",":{",":}",":-{",":-}",">:o~","^^","^_^","^-^",">_<","<_<",">_>",
							"=)", "=-)", "=]", "=-]", "=-}", "=}");
		foreach($smilies as $smiley) {
			$count+=substr_count(" ".$string." "," ".$smiley." ");
		}
		return $count;
	}
	
	static function normalizeString($string) {
		$string = trim($string);
		$charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';
		
		$string = self::convertUmlaute($string);
		
		$new_string = '';
		for($i=0;$i<strlen($string);$i++) {
			if(strstr($charset, $string{$i})) {
				$new_string.= $string{$i};
			} else {
				$new_string.= '_';
			}
		}
		
		while(false !== strstr($new_string,'__')) $new_string = str_replace('__','_',$new_string);
		
	return $new_string;
	}
	
	static function startsWith($needle, $haystack) {
		return substr($haystack, 0, strlen($needle)) == $needle;
	}
	
	static function endsWith($needle, $haystack) {
		return substr($haystack, -strlen($needle)) == $needle;
	}
	
	static function plural($word, $num) {
		$num = (int)($num);
		
		switch($word) {
			case 'time':
				switch($num) {
					case 1:  return 'once';
					case 2:  return 'twice';
					case 3:  return 'thrice';
				}
			break;
			case 'have': case 'has':
				if($num == 1) return 'has';
				else return 'have';
			break;
		}
		
		if($num != 1) $word.= 's';
		
	return $num.' '.$word;
	}
	
	static function getUrls($string) {
		preg_match_all('#(https?://.+?)(?:\s|$)#i', $string, $arr);
	return $arr[1];
	}
}
?>
