<?php

class Hackquest extends ChallengeStats {
	
	public $triggers = array('!hq', '!hackquest');
	
	protected $url        = 'http://hackquest.com';
	protected $profileUrl = 'http://hackquest.com/user.php?op=userinfo&uname=%s';
	protected $statsText  = '{username} is a {user_title} at Hackquest with {challs} challenges solved and {score} rankpoints. {username} visited {url} {visits} times and spent a total of {time} there. {username} was last online {last_online}.';
	
	function getStats($username, $html) {
		if (strpos($html, 'There is no available information for') !== false)
			return false;

		preg_match('#<h3>User information of (VIP )?(.*?)</h3>#',$html,$arr);
		$nick = $arr[2];

		preg_match('#<b>Rank:</b>.*?<font color=".*?">(.*?)</font>#',$html,$arr);
		$rank = $arr[1];

		preg_match('#<b>Number of hacks:</b>.*?<td>(.*?)</td>#',$html,$arr);
		$solved = $arr[1];

		preg_match('#<b>Rankpoints:</b>.*?<td>(.*?)</td>#',$html,$arr);
		$rankpoints = $arr[1];

		preg_match('#<b>Visited:</b>.*?<td>(.*?)</td>#',$html,$arr);
		$visited = $arr[1];

		preg_match('#<b>Time spent overall:</b>.*?<td>(.*?)</td>#',$html,$arr);
		$timeSpent = $arr[1];

		preg_match('#<b>Last online:</b>.*?<td>(.*?)</td>#',$html,$arr);
		$lastOnline = trim($arr[1]);
		
		$data = array(
			'username'  => $nick,
			'user_title' => $rank,
			'challs' 	=> $solved,
			'score'		=> $rankpoints,
			'visits'	=> $visited,
			'time'		=> libTime::secondsToString($timeSpent),
			'last_online' => $lastOnline
		);
		
	return $data;
	}
	
}

?>
