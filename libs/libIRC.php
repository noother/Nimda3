<?php

class libIRC {
	
	static function noHighlight($nick) {
		$pos = rand(1, strlen($nick)-1);
		$newnick = substr($nick, 0, $pos)."\xC2\xAD".substr($nick, $pos);
	return $newnick;
	}
	
}

?>
