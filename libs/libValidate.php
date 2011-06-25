<?php

class libValidate {
	
	static function date($string) {
		return $string == date('Y-m-d',strtotime($string));
	}
	
	static function email($string) {
		if(!preg_match('/^[a-z0-9][a-z0-9_\.+-]*@[a-z0-9\-\.]+\.[a-z]{2,}$/i',$string)) return false;
	return true;
	}
	
	static function integer($mixed) {
		if((int)$mixed != $mixed) return false;
	return true;
	}
	
	static function strongPassword($password) {
		if(strlen($password) < 6) return false;
	return true;
	}
	
	static function creditCard($number) {
		for($x=0;$x<strlen($number);$x+=2) {
			$double = $number[$x]*2;
			if($double > 9) $double = libMath::checksum($double);
			$number[$x] = $double;
		}
	
		if(!(libMath::checksum($number)%10)) return true;
	return false;
	}
	
	
}

?>
