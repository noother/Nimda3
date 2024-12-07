<?php

namespace Nimda\Plugin\User\ChallengeStats;

class MibsChallenges extends ChallengeStats {
	
	public $triggers = array('!mbc', '!mib', '!mibs-challenges');
	
	protected $url        = 'http://mibs-challenges.de';
	protected $profileUrl = 'http://mibs-challenges.de/userinfo.php?profile=%s';
	
	function getStats($username, $html) {
		if(str_starts_with($html, 'This user doesn\'t exist!')) return false;
		
		preg_match('/^(.+?) has solved (\d+) out of (\d+) challenges.(?: He is at position (\d+?) out of (\d+)!)?$/', $html, $arr);
	
		$data = array(
			'username'      => $arr[1],
			'challs_solved' => $arr[2],
			'challs_total'  => $arr[3],
			'rank'          => isset($arr[4]) ? $arr[4] : false,
			'users_total'   => isset($arr[5]) ? $arr[5] : false
		);
		
		if(!$data['rank']) {
			$this->statsText = '{username} solved {challs_solved} (of {challs_total}) challenges. There is no information available about his/her rank at {url}';
		}
		
	return $data;
	}
	
}

?>
