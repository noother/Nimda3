<?php

class Hacker extends ChallengeStats {
	
	public $triggers = array('!hacker');
	
	protected $url = 'http://hacker.org/';
	protected $statsText = '{username} solved {challs} challenges and has a score of {score} points at {url}{extra}';
	
	function getStats($username) {
		// TODO cache following page!!
		$res = libHTTP::GET('www.hacker.org', '/challenge/top.php');

		if (!preg_match('#a href="/forum/profile\.php\?mode=viewprofile&u=([0-9]+)">'.$username.'</a></td> <td>(.*?)</td><td>(.*?)</td>#i', $res['raw'], $arr))
			return false;

		$score = $arr[2];
		$solved = $arr[3];

		$extra = array();
		$res = libHTTP::GET('www.hacker.org', '/forum/profile.php?mode=viewprofile&u='.$arr[1]);
		if (preg_match('#<td>([0-9]+)</td><td><a href="/coil/">#', $res['raw'], $arr))
			$extra[] = 'Mortal Coil: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/modulo/">#', $res['raw'], $arr))
			$extra[] = 'Modulo: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/runaway/">#', $res['raw'], $arr))
			$extra[] = 'Runaway: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/brick/">#', $res['raw'], $arr))
			$extra[] = 'Bricolage: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/oneofus/">#', $res['raw'], $arr))
			$extra[] = 'OneOfUs: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/push/">#', $res['raw'], $arr))
			$extra[] = 'Pusherboy: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/tapeworm/">#', $res['raw'], $arr))
			$extra[] = 'Tapeworm: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/cross/">#', $res['raw'], $arr))
			$extra[] = 'Crossflip: '.$arr[1];

		$data = array(
			'username'  => $username,
			'challs' 	=> $solved,
			'score'		=> $score,
			'extra'		=> count($extra)?' Puzzle Highs: '.implode(', ',$extra):''
		);
		
	return $data;
	}
	
}

?>
