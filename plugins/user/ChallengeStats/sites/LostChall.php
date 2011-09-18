<?php

class LostChall extends ChallengeStats {
	
	public $triggers = array('!lost', '!lostchall');
	
	protected $url = 'http://lost-chall.org';
	protected $statsText = '{username} solved {challs} (of {challs_total}) challenges and is on rank {rank} with {points} points at {url}';
	
	function getStats($username) {
		$res = libHTTP::GET('lost-chall.org', '/user.php?user='.urlencode($username));

		if (strpos($res['raw'], 'User not found!') !== false)
			return false;

		preg_match('# is ranked: (.*?)<br#',$res['raw'],$arr);
		$rank = $arr[1];

		preg_match('#Points: (.*?)<br#',$res['raw'],$arr);
		$score = $arr[1];

		$solved = substr_count($res['raw'], '<span class="Stil9">');
		$challs_total = $solved + substr_count($res['raw'], '<span class="Stil5">');		
		
		$data = array(
			'username'  => $username,
			'challs' 	=> $solved,
			'challs_total' => $challs_total,
			'points'	=> $score,
			'rank'		=> $rank
		);
		
	return $data;
	}
	
}

?>
