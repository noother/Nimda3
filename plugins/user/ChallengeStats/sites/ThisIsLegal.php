<?php

class ThisIsLegal extends ChallengeStats {
	
	public $triggers = array('!til', '!thisislegal');
	
	protected $url = 'http://thisislegal.com';
	protected $statsText = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) with {score} (of {score_total}) points at {url}';
	
	function getStats($username) {
		$res = libHTTP::GET('www.thisislegal.com', '/userscore.php?username='.urlencode($username));
		if($res['raw'] == '0') return false;

		$result = libHTTP::GET("www.thisislegal.com","/user/".urlencode($username));
		$challs = substr_count($result['raw'],'<font color=\'#00FF00\' face=\'Verdana\'>Yes</font>');
		
		$tmp = explode(':', $res['raw']);
		$data = array(
			'username'      => $username,
			'rank'          => $tmp[0],
			'users_total'   => $tmp[3],
			'challs_solved' => $challs,
			'challs_total'  => trim($tmp[4]),
			'score'			=> $tmp[1],
			'score_total'	=> $tmp[2]
		);
		
	return $data;
	}
	
}

?>
