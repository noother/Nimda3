<?php

class Hackquest extends ChallengeStats {
	
	public $triggers = array('!hq', '!hackquest');
	
	protected $url = 'http://hackquest.com/';
	protected $statsText = '{username} is a {user_title} at Hackquest with {challs} challenges solved and {score} rankpoints. {username} visited {url} {visits} times and spent a total of {time} there. {username} was last online {last_online}.';
	
	function getStats($username) {
		$res = libHTTP::GET('hackquest.com', '/user.php?op=userinfo&uname='.urlencode($username));

		if (strpos($res['raw'], 'There is no available information for') !== false)
			return false;

		preg_match('#<h3>User information of (VIP )?(.*?)</h3>#',$res['raw'],$arr);
		$nick = $arr[2];

		preg_match('#<b>Rank:</b>.*?<font color=".*?">(.*?)</font>#',$res['raw'],$arr);
		$rank = $arr[1];

		preg_match('#<b>Number of hacks:</b>.*?<td>(.*?)</td>#',$res['raw'],$arr);
		$solved = $arr[1];

		preg_match('#<b>Rankpoints:</b>.*?<td>(.*?)</td>#',$res['raw'],$arr);
		$rankpoints = $arr[1];

		preg_match('#<b>Visited:</b>.*?<td>(.*?)</td>#',$res['raw'],$arr);
		$visited = $arr[1];

		preg_match('#<b>Time spent overall:</b>.*?<td>(.*?)</td>#',$res['raw'],$arr);
		$timeSpent = $arr[1];

		preg_match('#<b>Last online:</b>.*?<td>(.*?)</td>#',$res['raw'],$arr);
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
