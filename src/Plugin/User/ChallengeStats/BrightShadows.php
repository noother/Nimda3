<?php

namespace Nimda\Plugin\User\ChallengeStats;

class BrightShadows extends ChallengeStats {
	
	public $triggers = array('!tbs', '!theblacksheep', '!bs', '!bright-shadows', '!brightshadows', '!blacksheep');
	
	protected $url        = 'http://bright-shadows.net';
	protected $profileUrl = 'http://bright-shadows.net/userdata.php?username=%s';
	
	
	function getStats($username, $html) {
		if($html == 'Unknown User') return false;
		
		$tmp = explode(':', $html);
		$data = array(
			'username'      => $tmp[0],
			'rank'          => $tmp[1],
			'users_total'   => $tmp[2],
			'challs_solved' => $tmp[3],
			'challs_total'  => $tmp[4]
		);

		# check for unranked users
		if ($data['rank'] == -1)
			$this->statsText = '{username} solved {challs_solved} (of {challs_total}) challenges and is not ranked at {url}';
		
	return $data;
	}
	
}

?>
