<?php

class RevolutionElite extends ChallengeStats {
	
	public $triggers = array('!rev', '!revolution', '!revolutionelite');
	
	protected $url        = 'http://sabrefilms.co.uk/revolutionelite';
	protected $profileUrl = 'http://sabrefilms.co.uk/revolutionelite/profile.php?user=%s';
	protected $statsText  = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} at {url}';

	
	function getStats($username, $html) {
		if(preg_match("#Challenges solved: (.*?) from a possible (.*?)  \(.*?Rank: (.*?) <br#s", $html, $arr)) {
			$solved = $arr[1];
			$total  = $arr[2];
			$rank   = $arr[3];
		
			$data = array(
				'username'  => $username,
				'challs_solved' 	=> $solved,
				'challs_total' 	=> $total,
				'rank'		=> $rank,
				'users_total'	=> false
			);
		
			return $data;
		} else {
			return false;
		}
	}
	
}

?>
