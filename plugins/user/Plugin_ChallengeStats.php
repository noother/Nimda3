<?php

class Plugin_ChallengeStats extends Plugin {
	
	public $triggers = array(
		'!arcade',
		'!mbc', '!mib', '!mibs-challenges',
		'!nf', '!netforce', '!net-force'
	);
	
	private $notfound = 'The requested user was not found. You can register at %s';
	
	function isTriggered() {
		$username = isset($this->data['text']) ? $this->data['text'] : $this->User->nick;
		$output = false;
		
		switch($this->data['trigger']) {
			case '!arcade':
				$url = 'http://arcade.noother.net';
				$data = libChallenges::nootherArcade($username);
				if($data) {
					$output = sprintf(
						'%s holds the highscores on %d (of %d) active games and is on rank %d (of %d) at %s',
							$data['username'],
							$data['challs_solved'],
							$data['challs_total'],
							$data['rank'],
							$data['users_total'],
							$url
					);
				}
			break;
			case '!mbc': case '!mib': case '!mibs-challenges':
				$url = 'http://mibs-challenges.de';
				$data = libChallenges::mibsChallenges($username);
				if($data) {
					$output = sprintf(
						'%s has solved %d (of %d) challenges. ',
							$data['username'],
							$data['challs_solved'],
							$data['challs_total']
					);
					
					if($data['rank']) {
						$output.= sprintf(
							'He is on rank %d (of %d) at ',
								$data['rank'],
								$data['users_total']
						);
					} else {
						$output.= 'There is no rank information available about him at ';
					}
					
					$output.= $url;
				}
			break;
			case '!nf': case '!netforce': case '!net-force':
				$url = 'http://netforce.nl';
				$data = libChallenges::netforce($username);
				if($data) {
					$output = sprintf(
						'%s solved %d (of %d) challenges and is on rank %d (of %d) at %s',
							$data['username'],
							$data['challs_solved'],
							$data['challs_total'],
							$data['rank'],
							$data['users_total'],
							$url
					);
				}
			break;
		}
		
		if($output) $this->reply($output);
		else $this->reply(sprintf($this->notfound, $url));
	}
	
}

?>
