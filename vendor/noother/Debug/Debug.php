<?php

namespace noother\Debug;

class Debug {

	public static function init() {
		if(!file_exists('debug')) mkdir('debug');
	}

	public static function get($name, $own_name=false) {
		self::init();
		if(!$own_name) $name = self::normalize($name);

		if(file_exists('debug/'.$name)) return file_get_contents('debug/'.$name);

	return false;
	}

	public static function put($name, $content, $own_name=false) {
		self::init();
		if(!$own_name) $name = self::normalize($name);

		file_put_contents('debug/'.$name, $content);

	return $content;
	}

	public static function normalize($name) {
		$valid = "0123456789abcdefghijklmnopqrstuvwxyz_";
		$name = strtolower($name);

		$new_name = "";
		for($i=0;$i<strlen($name);$i++) {
			if(strstr($valid, $name[$i]) !== false) $new_name.= $name[$i];
			else $new_name.= "_";
		}

		$new_name = substr($new_name, 0, 150); // Don't exceed 255 limit

	return $new_name."_".crc32($name);
	}
}
