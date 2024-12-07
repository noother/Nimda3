<?php

namespace Nimda\Plugin\User\ChallengeStats;

class NewbieContest extends ChallengeStats {
	
	public $triggers = array('!nbc', '!newbiecontest');
	
	protected $url        = 'http://www.newbiecontest.org';
	protected $profileUrl = 'http://www.newbiecontest.org/userscore.php?username=%s';
	protected $statsText  = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) with {score} (of {score_total}) points at {url}';
	
	function getStats($username, $html) {
		if($html == 'Member : unknown') return false;

		if(!preg_match('#Member : (.*?)<br>Ranking : (.*?)/(.*?)<br>Points : (.*?)/(.*?)<br>Challenges solved : (.*?)/(.*?)<br>#',$html,$arr))
			return false;
		
		$data = array(
			'username'      => $arr[1],
			'rank'          => $arr[2],
			'users_total'   => $arr[3],
			'score'			=> $arr[4],
			'score_total'	=> $arr[5],
			'challs_solved' => $arr[6],
			'challs_total'  => $arr[7],
		);
		
	return $data;
	}
	
}

?>
