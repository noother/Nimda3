<?php

namespace Nimda\Plugin\User\ChallengeStats;

class W3challs extends ChallengeStats {
	
	public $triggers = array('!w3c', '!w3challs');
	
	protected $url        = 'http://w3challs.com';
	protected $profileUrl = 'http://w3challs.com/profile/%s';
	protected $statsText  = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) with {points} (of {points_total}) points at {url}';

	
	function getStats($username, $html) {
		if(strpos($html, 'Ce membre n\'existe pas') !== false)
			return false;

		preg_match("#Position : </td><td>(.*?)/(.*?)</td>.*?Points : </td><td>(.*?)/(.*?) \(.*?Epreuves : </td><td>(.*?)/(.*?) \(#s", $html, $arr);
		
		$data = array(
			'username'      => $username,
			'rank'          => $arr[1],
			'users_total'   => $arr[2],
			'points'        => $arr[3],
			'points_total'  => $arr[4],
			'challs_solved' => $arr[5],
			'challs_total'  => $arr[6]
		);
		
	return $data;
	}
	
}

?>
