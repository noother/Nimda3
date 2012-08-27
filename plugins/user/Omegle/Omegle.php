<?php

require_once('classes/HTTP.php');

class Omegle {
	
	public $listener;
	
	protected $chatId = false;
	
	private $Stream;
	private $proxy = false;
	
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
	
	function __destruct() {
		$this->disconnect();
	}
	
}

?>
