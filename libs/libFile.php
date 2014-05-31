<?php

class libFile {
	
	static function stripDirs($dir,$num) {
		if(substr($dir,-1) == '/') $dir = substr($dir,0,-1);
		for($x=0;$x<$num;$x++) {
			$pos = strrpos($dir,'/');
			$dir = substr($dir,0,$pos);
		}
		
	return $dir;
	}
	
	static function parseConfigFile($file) {
		if(!file_exists($file)) return array();
		$array = array();
		$fp = fopen($file,'r');
		if(!$fp) return false;
		
		while(!feof($fp)) {
			$row = trim(fgets($fp));
			if(preg_match('/^([a-zA-Z0-9_.\*-]+?)\s+=\s+(.+)$/i',$row,$arr)) {
				$array[$arr[1]] = $arr[2];
			} else if(preg_match('/^([a-zA-Z0-9_.\*-]+?)\s+=$/i',$row,$arr)) {
                 $array[$arr[1]] = null;
            }
		}
		
		fclose($fp);
	return $array;
	}
	
}


?>
