<?php

class DYM extends ChallengeStats {
	
	public $triggers = array('!dym', '!dareyourmind');
	
	protected $url = 'http://www.dareyourmind.net';
	
	function getStats($username) {
		$res = libHTTP::GET('www.dareyourmind.net', '/userscore.php?username='.urlencode($username));

		$tmp = explode(':', $res['raw']);
		if($tmp[1] == '0') return false;
		
		$data = array(
			'username'      => $username,
			'rank'          => $tmp[0],
			'users_total'   => $tmp[3],
			'challs_solved' => '~ '.round($tmp[4]*$tmp[1]/100),
			'challs_total'  => $tmp[4]
		);
		
	return $data;
	}
	
}

?>
