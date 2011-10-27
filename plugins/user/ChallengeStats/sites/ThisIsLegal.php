<?php

class ThisIsLegal extends ChallengeStats {
	
	public $triggers = array('!til', '!thisislegal');
	
	protected $url       = 'http://thisislegal.com';
	protected $statsText = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) with {score} (of {score_total}) points at {url}';
	
	function getStats($username, $html) {
		
		$HTTP = new HTTP('thisislegal.com');
		$html = $HTTP->GET('/userscore.php?username='.urlencode($username));
		if($html === false) return 'timeout';
		if($html === '0') return false;
		$info = explode(':', $html);
		
		$html = $HTTP->GET('/user/'.urlencode($username));
		if($html === false) return 'timeout';
		$challs = substr_count($html,'<font color=\'#00FF00\' face=\'Verdana\'>Yes</font>');
		
		$data = array(
			'username'      => $username,
			'rank'          => $info[0],
			'users_total'   => $info[3],
			'challs_solved' => $challs,
			'challs_total'  => trim($info[4]),
			'score'			=> $info[1],
			'score_total'	=> $info[2]
		);
		
	return $data;
	}
	
}

?>
