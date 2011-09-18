<?php

class RevolutionElite extends ChallengeStats {
	
	public $triggers = array('!rev', '!revolution', '!revolutionelite');
	
	protected $url = 'http://sabrefilms.co.uk/revolutionelite';
	protected $statsText    = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} at {url}';

	
	function getStats($username) {
		$res = libHTTP::GET('sabrefilms.co.uk', '/revolutionelite/profile.php?user='.urlencode($username));

		if (strpos($res['raw'], 'User Not Found') !== false)
			return false;

		preg_match("#Challenges solved: (.*?) from a possible (.*?)  \(#",$res['raw'],$arr);
		$solved = $arr[1];
		$total = $arr[2];

		preg_match("#Rank: (.*?) <br#",$res['raw'],$arr);
		$rank = $arr[1];
		
		$data = array(
			'username'  => $username,
			'challs_solved' 	=> $solved,
			'challs_total' 	=> $total,
			'rank'		=> $rank,
			'users_total'	=> false
		);
		
	return $data;
	}
	
}

?>
