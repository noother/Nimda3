<?php

class Netforce extends ChallengeStats {
	
	public $triggers = array('!nf', '!netforce', '!net-force');
	
	protected $url = 'http://net-force.nl';
	
	function getStats($username) {
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
