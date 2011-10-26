<?php

class RightAnswer extends ChallengeStats {
	
	public $triggers = array('!ra', '!rightanswer');
	
	protected $url        = 'http://www.right-answer.net';
	protected $profileUrl = 'http://www.right-answer.net/membres.php?pseudo=%s';

	
	function getStats($username, $html) {
		if (strpos($html, 'Ce membre n\'existe pas') !== false)
			return false;

		preg_match("#Position dans le classement: (.*?) / (.*?)<br#", $html, $arr);
		$rank  = $arr[1];
		$users = $arr[2];

		$solved = substr_count($html, '<img src="images/check.png"></a>');
		$total = $solved + substr_count($html, '<img src="images/cross.png"></a>');
		
		$data = array(
			'username'      => $username,
			'challs_solved' => $solved,
			'challs_total'  => $total,
			'rank'          => $rank,
			'users_total'   => $users
		);
		
	return $data;
	}
	
}

?>
