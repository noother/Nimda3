<?php

class libChallenges {
	
	static function nootherArcade($username) {
		$res = libHTTP::GET('arcade.noother.net', '/userinfo.php?username='.urlencode($username));
		if($res['raw'] == 'not found') return false;
		
		$data = array();
		$tmp = explode(':', $res['raw'], 5);
		
		$data = array(
			'challs_solved' => $tmp[0],
			'challs_total'  => $tmp[1],
			'rank'          => $tmp[2],
			'users_total'   => $tmp[3],
			'username'      => $tmp[4]
		);
		
	return $data;
	}
	
	static function mibsChallenges($username) {
		$res = libHTTP::GET("mibs-challenges.de","/userinfo.php?profile=".urlencode($username));
		if(libString::startsWith('This user doesn\'t exist!', $res['raw'])) return false;
		
		preg_match('/^(.+?) has solved (\d+) out of (\d+) challenges.(?: He is at position (\d+?) out of (\d+)!)?$/', $res['raw'], $arr);
	
		$data = array(
			'username'      => $arr[1],
			'challs_solved' => $arr[2],
			'challs_total'  => $arr[3],
			'rank'          => isset($arr[4]) ? $arr[4] : false,
			'users_total'   => isset($arr[5]) ? $arr[5] : false
		);
	return $data;
	}
	
	static function netforce($username) {
		$res = libHTTP::GET('net-force.nl', '/wechall/userscore.php?username='.urlencode($username));
		if(libString::startsWith('::', $res['raw'])) return false;
		
		$tmp = explode(':', $res['raw']);
		
		$data = array(
			'username'      => $username,
			'rank'          => $tmp[0],
			'challs_solved' => $tmp[1],
			'challs_total'  => $tmp[2],
			'users_total'   => $tmp[3]
		);
	
	return $data;
	}
	
}

?>
