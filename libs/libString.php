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
		$replace = array(' ' => '_',
						 "'" => '',
						 '-' => '_'
						);
		
		$string = self::convertUmlaute($string);
		
		$string = strtr($string,$replace);
		
		$newstring  = "";
		for($x=0,$len=mb_strlen($string);$x<$len;$x++) {
			$char = mb_substr($string,$x,1);
			if(mb_strstr($charset,$char)) $newstring.= $char;
		}
		
		while(strstr($newstring,'__')) $newstring = str_replace('__','_',$newstring);
		
		$newstring = strtolower($newstring);
		
	return $newstring;
	}
}
?>
