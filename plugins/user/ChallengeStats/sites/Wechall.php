<?php

class Wechall extends ChallengeStats {
	
	public $triggers = array('!wcc');
	
	protected $url = 'http://www.wechall.net';
	protected $statsText    = '{username} solved {challs_solved} of {challs_total} challenges with {points} of {points_total} possible points ({points_percent}%) and is on rank {rank} of {users_total} at {url}';
	
	function getStats($username) {
		$res = libHTTP::GET('www.wechall.net','/wechallchalls.php?username='.urlencode($username));
		if(libString::startsWith('The user', $res['raw'])) return false;
		
		preg_match('/^(.+?) solved (\d+?) of (\d+?) Challenges with (\d+?) of (\d+?) possible points \(([\d\.]+?)%\). Rank for the site WeChall: (\d+)$/', $res['raw'], $arr);
	
		$data = array(
			'username'       => $arr[1],
			'challs_solved'  => $arr[2],
			'challs_total'   => $arr[3],
			'points'         => $arr[4],
			'points_total'   => $arr[5],
			'points_percent' => $arr[6],
			'rank'           => $arr[7],
			'users_total'    => $this->getUsersTotal()
		);
		
	return $data;
	}
	
	private function getUsersTotal() {
		$res = libHTTP::GET('www.wechall.net', '/wechall.php?username=gizmore');
		preg_match('/is ranked \d+? from (\d+),/', $res['raw'], $arr);
	return $arr[1];
	}
	
}

?>
