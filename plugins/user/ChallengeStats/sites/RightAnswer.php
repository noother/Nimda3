<?php

class RightAnswer extends ChallengeStats {
	
	public $triggers = array('!ra', '!rightanswer');
	
	protected $url = 'http://www.right-answer.net/';

	
	function getStats($username) {
		$res = libHTTP::GET('www.right-answer.net', '/membres.php?pseudo='.urlencode($username));

		if (strpos($res['raw'], 'Ce membre n\'existe pas') !== false)
			return false;

		preg_match("#Position dans le classement: (.*?) / (.*?)<br#",$res['raw'],$arr);
		$rank = $arr[1];
		$users = $arr[2];

		$solved = substr_count($res['raw'], '<img src="images/check.png"></a>');
		$total = $solved + substr_count($res['raw'], '<img src="images/cross.png"></a>');
		
		$data = array(
			'username'  => $username,
			'challs_solved' 	=> $solved,
			'challs_total' 	=> $total,
			'rank'		=> $rank,
			'users_total'	=> $users
		);
		
	return $data;
	}
	
}

?>
