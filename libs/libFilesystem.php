<?php

class libFilesystem {
	
	static function getFiles($dir, $extension=false) {
		$files = array();
		
		$dir = opendir($dir);
		while($file = readdir($dir)) {
			if(in_array($file, array('.', '..'))) continue;
			if(is_dir($file)) continue;
			if($extension) {
				$tmp = explode('.', $file);
				if($tmp[sizeof($tmp)-1] != $extension) continue;
			}
			$files[] = $file;
		}
		
		sort($files);
		
	return $files;
	}
	
}

?>
