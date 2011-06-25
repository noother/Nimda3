<?php

class libCrypt {
	
	static function createSalt() {
		return substr(crypt(md5(get_microtime()),CRYPT_STD_DES),2,8);
	}
	
	static function getRandomHash() { 
		return md5(get_microtime());
	}
	
	static function createPassword() {
		return self::createSalt();
	}
	
}

?>
