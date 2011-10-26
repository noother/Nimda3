<?php

class Wechall extends ChallengeStats {
	
	public $triggers = array('!wcc');
	
	protected $url = 'https://www.wechall.net';
	protected $statsText    = '{username} solved {challs_solved} of {challs_total} challenges with {points} of {points_total} possible points ({points_percent}%) and is on rank {rank} of {users_total} at {url}';
	
	function getStats($username, $html) {
		
		$HTTP = new HTTP('www.wechall.net');
		$html = $HTTP->GET('/wechallchalls.php?username='.urlencode($username));
		if(libString::startsWith('The user', $html)) return false;
		
		preg_match('/^(.+?) solved (\d+?) of (\d+?) Challenges with (\d+?) of (\d+?) possible points \(([\d\.]+?)%\). Rank for the site WeChall: (\d+)$/', $html, $arr);
		
		$html = $HTTP->GET('/wechall.php?username=noother');
		preg_match('/is ranked \d+? from (\d+),/', $html, $arr2);
		
		$data = array(
			'username'       => $arr[1],
			'challs_solved'  => $arr[2],
			'challs_total'   => $arr[3],
			'points'         => $arr[4],
			'points_total'   => $arr[5],
			'points_percent' => $arr[6],
			'rank'           => $arr[7],
			'users_total'    => $arr2[1]
		);
		
	return $data;
	}
	
}

?>
