<?php

class libHTTP {

	static function GET($host,$get,$cookie=null,$timeout=30,$port=80) {
		$fp     = fsockopen($host,$port,$timeout);
		if(!$fp) return false;
		
		$header = "GET ".$get." HTTP/1.0\r\n";
		$header.= "Host: ".$host."\r\n";
		$header.= "User-Agent: Noothwork\r\n";
		if(isset($cookie)) $header.= "Cookie: ".$cookie."\r\n";
		
		fputs($fp,$header."\r\n");
		
		$headersCheck = true;
		$output = array();
		$output['content'] = array();
		
		while(false !== $row = fgets($fp)) {
			$row = trim($row);
			
			if($headersCheck && empty($row)) {
				$headersCheck = false;
				continue;
			}
			
			if($headersCheck) {
				$tmp = explode(": ",$row,2);
				$output['header'][$tmp[0]] = $tmp[1];
			} else {
				array_push($output['content'],$row);
			}
			
		}
		
		fclose($fp);
		
		$output['raw'] = implode("\n",$output['content']);
	return $output;
	}
	
	static function POST($host,$get,$post,$cookie=null,$timeout=30,$port=80) {
		$fp = fsockopen($host,$port,$timeout);
		if(!$fp) return false;
		
		$header = "POST ".$get." HTTP/1.0\r\n";
		$header.= "Host: ".$host."\r\n";
		$header.= "User-Agent: Noothwork\r\n";
		if(isset($cookie)) {
			$header.= "Cookie: ".$cookie."\r\n";
		}
		$header.= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header.= "Content-Length: ".strlen($post)."\r\n";
		$header.= "\r\n";
		$header.= $post."\r\n";
		
		fputs($fp,$header."\r\n");
		
		$headersCheck = true;
		$output = array();
		$output['content'] = array();
		while(false !== $row = fgets($fp)) {
			$row = trim($row);
			
			if($headersCheck && empty($row)) {
				$headersCheck = false;
				continue;
			}
			
			if($headersCheck) {
				$tmp = explode(": ",$row,2);
				$output['header'][$tmp[0]] = $tmp[1];
			} else {
				array_push($output['content'],$row);
			}
			
		}
		fclose($fp);
		$output['raw'] = implode("\n",$output['content']);
	return $output;
	}

}


?>
