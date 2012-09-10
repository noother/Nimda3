<?php

require_once('classes/HTTP.php');
require_once('libs/libInternet.php');
require_once('libs/libString.php');

class Omegle {
	
	public $listener;
	
	protected $chatId = false;
	
	private $Stream;
	private $proxy = false;
	
	private static $bannedHosts = array('ihookup.com', 'naughtybenaughty.com', 'omegleadult.info');
	
	function __construct() {
		$this->Stream = new HTTP('cardassia.omegle.com');
		if($this->proxy) $this->Stream->set('proxy', $this->proxy);
		$this->Stream->set('keep-alive', false);
	}
	
	function start() {
		if($this->chatId !== false) {
			trigger_error('Session already started');
			return false;
		}
		
		$this->chatId = json_decode($this->Stream->GET('/start'));
		if(!$this->chatId) return false;
		
		$this->listener = popen('/usr/bin/php ./plugins/user/Omegle/OmegleListener.php '.escapeshellarg($this->chatId), 'r');
		stream_set_blocking($this->listener, 0);
		
	return true;
	}
	
	function read() {
		if($this->chatId === false) return false;
		$res = $this->Stream->POST('/events', array('id' => $this->chatId));
		
		if(empty($res)) return $this->read();
		if($res === 'null') return false;
		
	return json_decode($res);
	}
	
	function send($text) {
		if($this->chatId === false) return false;
		
		if($this->checkSpam($text)) return 'spam';
		
		$res = $this->Stream->POST('/send', array('id' => $this->chatId, 'msg' => $text));
		if($res == 'win') return true;
		else return false;
	}
	
	function typing() {
		if($this->chatId === false) return false;
		
		$res = $this->Stream->POST('/typing', array('id' => $this->chatId));
		if($res == 'win') return true;
		else return false;
	}
	
	function stoppedTyping() {
		if($this->chatId === false) return false;
		
		$res = $this->Stream->POST('/stoppedtyping', array('id' => $this->chatId));
		if($res == 'win') return true;
		else return false;
	}
	
	function disconnect() {
		if($this->chatId === false) return false;
		
		$res = $this->Stream->POST('/disconnect', array('id' => $this->chatId));
		if($res == 'win') return true;
		else return false;
	}
	
	private function checkSpam($text) {
		$links = libString::getUrls($text);
		foreach($links as $link) {
			$parts = parse_url($link);
			if($parts['host'] == 'tinyurl.com' && isset($parts['path']) && !isset($path['query']) && ctype_alnum(substr($parts['path'], 1))) {
				if($this->checkSpam(libInternet::tinyURLDecode($link))) return true;
			} else {
				preg_match('/[^\.]+\.[^\.]+$/', $parts['host'], $arr);
				$domain = $arr[0];
				if(in_array($domain, self::$bannedHosts)) return true;
			}
		}
		
	return false;
	}
	
	function __destruct() {
		$this->disconnect();
	}
	
}

?>
