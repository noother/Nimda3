<?php

require_once('classes/HTTP.php');

class libHTTP {

	static function GET($url) {
		return self::_execute($url, 'GET');
	}
	
	static function POST($url, $post=array()) {
		return self::_execute($url, 'POST', $post);
	}
	
	static private function _execute($url, $method, $post=null) {
		$data = parse_url($url);
		
		if(isset($data['user']) || isset($data['pass'])) {
			// TODO: not yet implemented
			return false;
		}
		
		if(isset($data['query'])) {
			$data['fullpath'] = $data['path'].'?'.$data['query'];
		} else {
			$data['fullpath'] = $data['path'];
		}
		
		switch($data['scheme']) {
			case 'http':
				$HTTP = new HTTP($data['host'], (isset($data['port']) ? $data['port'] : 80));
			break;
			case 'https':
				$HTTP = new HTTP($data['host'], (isset($data['port']) ? $data['port'] : 443), true);
			break;
			default:
				return false;
			break;
		}
		
		switch($method) {
			case 'GET':
				return $HTTP->GET($data['fullpath']);
			break;
			case 'POST':
				return $HTTP->POST($data['fullpath'], $post);
			break;
			default:
				return false;
			break;
		}
	}

}


?>
