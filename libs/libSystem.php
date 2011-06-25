<?php

class libSystem {

	static function getMicrotime() {
		$tmp = microtime();
		$tmp = explode(" ",$tmp);
	return $tmp[0]+$tmp[1];
	}

}

?>
