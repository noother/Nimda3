<?php

namespace Nimda\Plugin\User\ChallengeStats;

class RevolutionElite extends ChallengeStats {
	// TODO: broken
	//public $triggers = array('!rev', '!revolution', '!revolutionelite');
	public $triggers = [];
	
	protected $url        = 'http://revolutionelite.co.uk';
	protected $profileUrl = 'https://www.sabrefilms.co.uk/revolutionelite/w3ch4ll/userscore.php?username=%s';
	#protected $statsText = '{username} solved {challs_solved} (of {challs_total}) challenges with {points} (of {points_total} points) and is on rank {rank} (of {users_total}) at {url}';
	# for now each chall scores 1 point so no sense in outputting solved challs AND score
	protected $statsText = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) at {url}';

	
	function getStats($username, $html) {
		if ($html === '0')
			return false;

		$tmp = explode(':', $html);
		
		$data = array(
			'username'  	=> $tmp[0],
			'rank'			=> $tmp[1]+1,
			'points'		=> $tmp[2],
			'points_total'	=> $tmp[3],
			'challs_solved' => $tmp[4],
			'challs_total' 	=> $tmp[5],
			'users_total'	=> $tmp[6]
		);
		
		return $data;
	}
	
}

?>
