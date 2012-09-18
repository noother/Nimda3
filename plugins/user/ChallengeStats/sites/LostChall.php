<?php

class LostChall extends ChallengeStats {
	
	public $triggers = array('!lost', '!lostchall');
	
	protected $url        = 'http://lost-chall.org';
	protected $profileUrl = 'http://lost-chall.org/user.php?user=%s';
	protected $statsText  = '{username} solved {challs} (of {challs_total}) challenges and is on rank {rank} with {points} points at {url}';
	
	function getStats($username, $html) {
		if (strpos($html, 'User not found!') !== false)
			return false;

		preg_match('# is ranked: (.*?)<br#',$html, $arr);
		$rank = $arr[1];

		preg_match('#Points: (.*?)<br#',$html, $arr);
		$score = $arr[1];

		$solved = substr_count($html, ' class="Stil9"');
		$challs_total = $solved + substr_count($html, ' class="Stil5"');		
		
		$data = array(
			'username'     => $username,
			'challs' 	   => $solved,
			'challs_total' => $challs_total,
			'points'	   => $score,
			'rank'		   => $rank
		);
		
	return $data;
	}
	
}

?>
