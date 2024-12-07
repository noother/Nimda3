<?php

namespace Nimda\Plugin\User\ChallengeStats;

use noother\Network\HTTP;

class ThisIsLegal extends ChallengeStats {
	
	public $triggers = array('!til', '!thisislegal');
	
	protected $url       = 'http://www.thisislegal.com';
	protected $statsText = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) with {score} (of {score_total}) points at {url}';
	
	function getStats($username, $html) {
		
		$HTTP = new HTTP('www.thisislegal.com', true);
		$html = $HTTP->GET('/userscore.php?username='.urlencode($username));
		if($html === false) return 'timeout';
		if($html === '0') return false;
		list($rank, $score, $score_total, $users_total) = explode(':', $html);
		
		$html = $HTTP->GET('/user/'.urlencode($username));
		if($html === false) return 'timeout';
		
		preg_match('#Username: <font class="cf">\s+(.+?)</font>#', $html, $arr);
		list($full, $username) = $arr;
		
		preg_match('#<b>% Completed&nbsp;\((\d+?)/(\d+?)\):&nbsp;</b>#', $html, $arr);
		list($full, $challs_solved, $challs_total) = $arr;
		
		$data = array(
			'username'      => $username,
			'rank'          => $rank,
			'users_total'   => $users_total,
			'challs_solved' => $challs_solved,
			'challs_total'  => $challs_total,
			'score'			=> $score,
			'score_total'	=> $score_total
		);
		
	return $data;
	}
	
}

?>
