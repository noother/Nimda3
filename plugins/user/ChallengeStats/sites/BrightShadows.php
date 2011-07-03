<?php

class BrightShadows extends ChallengeStats {
	
	public $triggers = array('!tbs', '!theblacksheep', '!bs', '!bright-shadows', '!brightshadows', '!blacksheep');
	
	protected $url = 'http://bright-shadows.net';
	
	function getStats($username) {
		$res = libHTTP::GET('bright-shadows.net', '/userdata.php?username='.urlencode($username));
		if($res['raw'] == 'Unknown User') return false;
		
		$tmp = explode(':', $res['raw']);
		$data = array(
			'username'      => $tmp[0],
			'rank'          => $tmp[1],
			'users_total'   => $tmp[2],
			'challs_solved' => $tmp[3],
			'challs_total'  => $tmp[4]
		);
		
	return $data;
	}
	
}

?>
