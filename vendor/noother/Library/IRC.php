<?php

namespace noother\Library;

class IRC {
	public static $unicodeLookalikes = array(
		'A' => "\xce\x91", 'B' => "\xce\x92", 'C' => "\xd0\xa1", 'E' => "\xce\x95", 'F' => "\xcf\x9c",
		'H' => "\xce\x97", 'I' => "\xce\x99", 'J' => "\xd0\x88", 'K' => "\xce\x9a", 'M' => "\xce\x9c",
		'N' => "\xce\x9d", 'O' => "\xce\x9f", 'P' => "\xce\xa1", 'S' => "\xd0\x85", 'T' => "\xce\xa4",
		'X' => "\xce\xa7", 'Y' => "\xce\xa5", 'Z' => "\xce\x96",
		
		'a' => "\xd0\xb0", 'c' => "\xd1\x81", 'e' => "\xd0\xb5", 'i' => "\xd1\x96", 'j' => "\xd1\x98",
		'o' => "\xd0\xbe", 'p' => "\xd1\x80", 's' => "\xd1\x95", 'x' => "\xd1\x85", 'y' => "\xd1\x83"
	);
	
	public static function noHighlight($nick) {
		if(false === $newnick = self::placeUnicode($nick)) {
			$newnick = self::placeSofthyphen($nick);
		}
	
	return $newnick;
	}
	
	public static function placeUnicode($nick) {
		for($i=0;$i<mb_strlen($nick);$i++) {
			$char = mb_substr($nick, $i, 1);
			if(isset(self::$unicodeLookalikes[$char])) {
				return mb_substr($nick, 0, $i).self::$unicodeLookalikes[$char].mb_substr($nick, $i+1);
			}
		}
	
	return false;
	}
	
	public static function placeSofthyphen($nick) {
		$pos = rand(1, mb_strlen($nick)-1);
		$newnick = mb_substr($nick, 0, $pos)."\xC2\xAD".mb_substr($nick, $pos);
	return $newnick;
	}
	
	public static function stripControlChars($message) {
		$new_message = "";
		for($i=0;$i<strlen($message);$i++) {
			$ascii = ord($message[$i]);
			if($ascii > 31 || $ascii == 1) {
				$new_message.= $message[$i];
			}
			
			if($ascii == 3) $i++;
			
		}
		
	return $new_message;
	}
	
}

?>
