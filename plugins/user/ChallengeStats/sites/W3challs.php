<?php

class W3challs extends ChallengeStats {
	
	public $triggers = array('!w3c', '!w3challs');
	
	protected $url = 'http://w3challs.com/';
	protected $statsText    = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) with {points} (of {points_total}) points at {url}';

	
	function getStats($username) {
		$res = libHTTP::GET('w3challs.com', '/profile/'.urlencode($username));

		if (strpos($res['raw'], 'Ce membre n\'existe pas') !== false)
			return false;

		preg_match("#Epreuves : </td><td>(.*?)/(.*?) \(#",$res['raw'],$arr);
		$solved = $arr[1];
		$total = $arr[2];

		preg_match("#Position : </td><td>(.*?)/(.*?)</td>#",$res['raw'],$arr);
		$rank = $arr[1];
		$users = $arr[2];

		preg_match("#Points : </td><td>(.*?)/(.*?) \(#",$res['raw'],$arr);
		$points = $arr[1];
		$points_total = $arr[2];
		
		$data = array(
			'username'  => $username,
			'challs_solved' 	=> $solved,
			'challs_total' 	=> $total,
			'rank'		=> $rank,
			'users_total'	=> $users,
			'points' => $points,
			'points_total' => $points_total
		);
		
	return $data;
	}
	
}

?>
