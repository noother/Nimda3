<?php

class Rankk extends ChallengeStats {
	
	public $triggers = array('!rk', '!rankk', '!pyramid');
	
	protected $url = 'http://www.rankk.org/';
	protected $statsText = '{username} is a {user_title} rankked with {score} points and {challs} challenges solved. {username} is at level {level} at {url}';
	
	function getStats($username) {
		$res = libHTTP::GET('www.rankk.org', '/user/'.urlencode($username));

		if (strpos($res['raw'], '<h1>User Not Found</h1>') !== false)
			return false;

		preg_match("#>Rankk Title</td><td>(.*?)</td>#",$res['raw'],$arr);
		$title = $arr[1];

		preg_match("#>Rankked</td><td>(.*?)</td>#",$res['raw'],$arr);
		$rank = $arr[1];

		preg_match("#>Points</td><td>(.*?)</td>#",$res['raw'],$arr);
		$points = $arr[1];

		preg_match("#>Solved</td><td>(.*?)</td>#",$res['raw'],$arr);
		$solved = $arr[1];

		preg_match("#>Points</td><td>(.*?)</td>#",$res['raw'],$arr);
		$points = $arr[1];

		preg_match("#>Level</td><td>(.*?)</td>#",$res['raw'],$arr);
		$level = $arr[1];
		
		$data = array(
			'username'  => $username,
			'user_title' => $title,
			'challs' 	=> $solved,
			'score'		=> $points,
			'level'	=> $level,
		);
		
	return $data;
	}
	
}

?>
