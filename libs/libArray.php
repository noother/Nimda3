<?php

class libArray {
	
	static function sortByLengthASC($array) {
		$tempFunction = create_function('$a,$b','return strlen($a)-strlen($b);');
		usort($array,$tempFunction);
	return $array;
	}
	
	static function sortByLengthDESC($array) {
		$tempFunction = create_function('$a,$b','return strlen($b)-strlen($a);');
		usort($array,$tempFunction);
	return $array;
	}
	
}

?>
