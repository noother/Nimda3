<?php

namespace Nimda\Plugin\User\ChallengeStats;

class Netforce extends ChallengeStats {
	
	public $triggers = array('!nf', '!netforce', '!net-force');
	
	protected $url        = 'http://net-force.nl';
	protected $profileUrl = 'http://net-force.nl/wechall/userscore.php?username=%s';
	
	function getStats($username, $html) {
		if(str_starts_with($html, '::')) return false;
		
		$tmp = explode(':', $html);
		
		$data = array(
			'username'      => $username,
			'rank'          => $tmp[0],
			'challs_solved' => $tmp[1],
			'challs_total'  => $tmp[2],
			'users_total'   => $tmp[3]
		);
	
	return $data;
	}
	
}

?>
