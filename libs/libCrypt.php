<?php

class libCrypt {
	
	static function createSalt() {
		return substr(crypt(md5(rand()),CRYPT_STD_DES),2,8);
	}
	
	static function getRandomHash() { 
		return md5(rand());
	}
	
	static function createPassword() {
		return self::createSalt();
	}
	
}

?>
