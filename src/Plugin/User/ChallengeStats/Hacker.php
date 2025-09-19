<?php

namespace Nimda\Plugin\User\ChallengeStats;

use noother\Network\HTTP;

class Hacker extends ChallengeStats {
	
	public $triggers = array('!hacker');
	
	protected $url       = 'http://hacker.org';
	protected $statsText = '{username} solved {challs} challenges and has a score of {score} points at {url}{extra}';
	
	function getStats($username, $html) {
		
		$HTTP = new HTTP('www.hacker.org', true);
		
		if(false === $top = $this->getCache()) {
			$top = $HTTP->GET('/challenge/top.php');
			if($top === false) return 'timeout';
			$this->putCache($top);
		}

		if (!preg_match('#a href="/forum/profile\.php\?mode=viewprofile&u=([0-9]+)">'.$username.'</a></td> <td>(.*?)</td><td>(.*?)</td>#i', $top, $arr))
			return false;

		$score  = $arr[2];
		$solved = $arr[3];

		$extra = array();
		$html = $HTTP->GET('/forum/profile.php?mode=viewprofile&u='.$arr[1]);
		if($html === false) return 'timeout';
		
		if (preg_match('#<td>([0-9]+)</td><td><a href="/coil/">#', $html, $arr))
			$extra[] = 'Mortal Coil: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/modulo/">#', $html, $arr))
			$extra[] = 'Modulo: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/runaway/">#', $html, $arr))
			$extra[] = 'Runaway: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/brick/">#', $html, $arr))
			$extra[] = 'Bricolage: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/oneofus/">#', $html, $arr))
			$extra[] = 'OneOfUs: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/push/">#', $html, $arr))
			$extra[] = 'Pusherboy: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/tapeworm/">#', $html, $arr))
			$extra[] = 'Tapeworm: '.$arr[1];
		if (preg_match('#<td>([0-9]+)</td><td><a href="/cross/">#', $html, $arr))
			$extra[] = 'Crossflip: '.$arr[1];

		$data = array(
			'username'  => $username,
			'challs' 	=> $solved,
			'score'		=> $score,
			'extra'		=> count($extra)?' - '.$username."'s puzzle scores: ".implode(', ',$extra):''
		);
		
	return $data;
	}
	
}

?>
