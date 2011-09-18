<?php

abstract class ChallengeStats {
	
	public $triggers = array();
	
	protected $url = false;
	protected $notfoundText = 'The requested user was not found. You can register at %s';
	protected $statsText    = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) at {url}';
	
	abstract protected function getStats($username);
	
	public final function getData($username) {
		if(!$this->url) return false;
		if(empty($this->triggers)) return false;
		
		if(false !== $stats = $this->getStats($username)) {
			$result = $this->getStatsText($stats);
		} else {
			$result = sprintf($this->notfoundText, $this->url);
		}
		
	return $result;
	}
	
	private function getStatsText($data) {
		$replace_pairs = array('{url}' => $this->url);
		
		foreach($data as $key => $value) {
			$replace_pairs['{'.$key.'}'] = $value;
		}
		
		$text = strtr($this->statsText, $replace_pairs);
		
	return $text;
	}
	
	protected final function getCache($lifetime=86400) {
		$path = 'plugins/user/ChallengeStats/cache/'.get_class($this).'.cache';
		if(file_exists($path) && time() - filemtime($path) < $lifetime) {
			return file_get_contents($path);
		}
		
	return false;
	}
	
	protected final function putCache($data) {
		$path = 'plugins/user/ChallengeStats/cache/'.get_class($this).'.cache';
		file_put_contents($path, $data);
		clearstatcache();
	}
	
}

?>
