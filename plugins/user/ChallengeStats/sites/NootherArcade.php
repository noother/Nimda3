<?php

class NootherArcade extends ChallengeStats {
	
	public $triggers = array('!arcade');
	
	protected $url        = 'http://arcade.noother.net';
	protected $profileUrl = 'http://arcade.noother.net/userinfo.php?username=%s';
	protected $statsText  = '{username} holds the highscores on {games_leader} (of {games_total}) active games and is on rank {rank} (of {users_total}) at {url}';
	
	function getStats($username, $html) {
		if($html == 'not found') return false;
		
		$tmp = explode(':', $html, 5);
		$data = array(
			'games_leader' => $tmp[0],
			'games_total'  => $tmp[1],
			'rank'         => $tmp[2],
			'users_total'  => $tmp[3],
			'username'     => $tmp[4]
		);
		
	return $data;
	}
	
}

?>
