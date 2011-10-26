<?php

class InfoMirmo extends ChallengeStats {
	
	public $triggers = array('!im', '!infomirmo');
	
	protected $url        = 'http://www.infomirmo.fr';
	protected $profileUrl = 'http://www.infomirmo.fr/challenge/statchallenger.php?user=%s';
	protected $statsText  = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) with {points} (of {points_total}) points at {url}';
	
	function getStats($username, $html) {
		if (strpos($html, 'Aucun membre ne porte ce pseudo') !== false)
			return false;

		preg_match('#Rang :</font></b> (.*?) sur (.*?)<br#', $html, $arr);
		$rank = $arr[1];
		$users_total = $arr[2];

		preg_match('#Point :</font> (.*?)</b> points sur (.*?) disponibles<br#', $html, $arr);
		$points = $arr[1];
		$points_total = $arr[2];

		preg_match('#Mission :</font> (.*?)</b> &eacute;preuves sur (.*?) disponibles<br#', $html, $arr);
		$challs = $arr[1];
		$challs_total = $arr[2];


		
		$data = array(
			'username'      => $username,
			'challs_solved' => $challs,
			'challs_total'  => $challs_total,
			'rank'          => $rank,
			'users_total'   => $users_total,
			'points'		=> $points,
			'points_total'	=> $points_total
		);
		
	return $data;
	}
	
}

?>
