<?php

class libMath {
	
	static function checksum($n) {
		$sum = 0;
		for($x=0;$x<strlen($n);$x++) $sum+= substr($n,$x,1);
	return $sum;
	}
	
}

?>
