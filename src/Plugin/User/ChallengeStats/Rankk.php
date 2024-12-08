<?php

namespace Nimda\Plugin\User\ChallengeStats;

class Rankk extends ChallengeStats {
	
	// TODO: broken
	//public $triggers = array('!rk', '!rankk', '!pyramid');
	public $triggers = [];
	
	protected $url        = 'http://www.rankk.org';
	protected $profileUrl = 'http://www.rankk.org/user/%s';
	protected $statsText  = '{username} is a {user_title} rankked with {score} points and {challs} challenges solved. {username} is at level {level} at {url}';
	
	function getStats($username, $html) {
		if(strpos($html, '<h1>User Not Found</h1>') !== false)
			return false;

		preg_match("#>Rankk Title</td><td>(.*?)</td>#", $html, $arr);
		$title = $arr[1];

		preg_match("#>Rankked</td><td>(.*?)</td>#", $html, $arr);
		$rank = $arr[1];

		preg_match("#>Points</td><td>(.*?)</td>#", $html, $arr);
		$points = $arr[1];

		preg_match("#>Solved</td><td>(.*?)</td>#", $html, $arr);
		$solved = $arr[1];

		preg_match("#>Points</td><td>(.*?)</td>#", $html, $arr);
		$points = $arr[1];

		preg_match("#>Level</td><td>(.*?)</td>#", $html, $arr);
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
