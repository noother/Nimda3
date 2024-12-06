<?php

use noother\Network\SimpleHTTP;

abstract class ChallengeStats {
	
	private $cachedir;
	
	public $triggers = array();
	
	protected $url          = false;
	protected $profileUrl   = false;
	protected $notfoundText = 'The requested user was not found. You can register at %s';
	protected $timeoutText  = 'Timeout on contacting %s';
	protected $statsText    = '{username} solved {challs_solved} (of {challs_total}) challenges and is on rank {rank} (of {users_total}) at {url}';
	
	abstract protected function getStats($username, $html);
	
	public final function __construct($cachedir=null) {
		if(isset($cachedir)) $this->cachedir = $cachedir;
	}
	
	public final function getData($username) {
		if(!$this->url) return false;
		if(empty($this->triggers)) return false;
		
		if($this->profileUrl !== false) {
			$html = SimpleHTTP::GET(sprintf($this->profileUrl, urlencode($username)));
		} else {
			$html = null;
		}
		
		if($html === false || 'timeout' === $stats = $this->getStats($username, $html)) {
			$result = sprintf($this->timeoutText, $this->url);
		} elseif($stats === false) {
			$result = sprintf($this->notfoundText, $this->url);
		} else {
			$result = $this->getStatsText($stats);
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
	
	public final function getUrl() {
		return $this->url;
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
