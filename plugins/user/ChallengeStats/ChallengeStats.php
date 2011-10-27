<?php

abstract class ChallengeStats {
	
	private $cachedir;
	
	public $triggers = array();
	
	protected $url          = false;
	protected $profileUrl   = false;
	protected $notfoundText = 'The requested user was not found. You can register at %s';
	protected $statsText    = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) at {url}';
	
	abstract protected function getStats($username, $html);
	
	public final function __construct($cachedir) {
		$this->cachedir = $cachedir;
	}
	
	public final function getData($username) {
		if(!$this->url) return false;
		if(empty($this->triggers)) return false;
		
		if(false !== $stats = $this->getStats($username, $this->profileUrl !== false ? libHTTP::GET(sprintf($this->profileUrl, urlencode($username))) : null)) {
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
		$path = $this->cachedir.'/challstats_'.get_class($this);
		if(file_exists($path) && time() - filemtime($path) < $lifetime) {
			return file_get_contents($path);
		}
		
	return false;
	}
	
	protected final function putCache($data) {
		$path = $this->cachedir.'/challstats_'.get_class($this);
		file_put_contents($path, $data);
		clearstatcache();
	}
	
}

?>
