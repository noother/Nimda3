<?php

class ZMachine {
	
	public $score = 0;
	public $moves = 0;
	public $location = '';
	public $isReady = false;
	
	private $process;
	private $pipes;
	private $_buffer;
	private $_lastProcessed;
	
	public $log;
	
	const DFROTZ_PATH = 'dfrotz'; // supply an absolute path here if it's not in your $PATH
	
	function __construct($gamefile) {
		$pathinfo = pathinfo(realpath($gamefile));
		
		$this->process = proc_open(
			'trap "" 2 && stdbuf -o0 '.self::DFROTZ_PATH.' -h 255 -w 255 -Z 0 '.$pathinfo['basename'].' 2>&1',
			array(array('pipe', 'r'), array('pipe', 'w')),
			$this->pipes,
			$pathinfo['dirname']
		);
		usleep(50000);
		
		$status = proc_get_status($this->process);
		if($status['running']) {
			$this->isReady = true;
		}
		
		stream_set_blocking($this->pipes[1], 0);
	}
	
	function read() {
		if($this->_buffer !== false) {
			$row = $this->_buffer;
			$this->_buffer = false;
		} else {
			$row = '';
			while(!feof($this->pipes[1]) && '' !== $buf = fread($this->pipes[1], 1)) {
				$row.= $buf;
				if($buf == "\n") break;
			}
			if($row === '') return false;
		}
		
		$row = trim($row);
		
		// Z-Games usually use proper capitalization, so let's assume, that if a line starts with a small letter, that line belongs to the previous line and only got sent in a new line because of the 255 chars line length limit. (We can handle more nowadays..)
		while(true) {
			$next_line = fgets($this->pipes[1]);
			if($next_line === false) break;
			
			if(preg_match('/^\s*[a-z]/', $next_line)) {
				$row.= ' '.trim($next_line);
			} else {
				$this->_buffer = $next_line;
				break;
			}
		}
		
		while(strstr($row, '  ')) $row = str_replace('  ', ' ', $row);
		if(preg_match('/^(?:> ?)+(.+)$/', $row, $arr)) {
			$row = $arr[1];
		}
		if(strlen($row) == 1) $row = '';
		
	return $row;
	}
	
	function write($text) {
		fputs($this->pipes[0], $text."\n");
	}
	
	function getData() {
		$messages = array();
		
		while(false !== $row = $this->read($this->pipes[1])) {
			if(false !== $msg = $this->processText($row)) $messages[] = $msg;
		}
		if(!empty($messages)) {
			// get rid off leading and trailing empty lines
			if(empty($messages[0]['text'])) array_shift($messages);
			if(empty($messages[sizeof($messages)-1]['text'])) array_pop($messages);
			
			return $messages;
		}
		
		$status = proc_get_status($this->process);
		if(!$status['running']) {
			foreach($this->pipes as $pipe) fclose($pipe);
			proc_close($this->process);
			
			$this->isReady = false;
			return array(array('type' => 'close'));
		}
		
		
	return array();
	}
	
	function processText($text) {
		if(empty($text) && empty($this->_lastProcessed)) return false; // Never send 2 empty lines in a row
		
		$this->_lastProcessed = $text;
		
		if(preg_match('/^(.+?) Score: ([\d]+?) Moves: ([\d]+)$/', $text, $arr)) {
			$this->location = $arr[1];
			$old_score = $this->score;
			$this->score = (int)$arr[2];
			$this->moves = (int)$arr[3];
			
			if($this->score != $old_score) {
				return array('type' => 'score_change', 'text' => 'foo', 'old_score' => $old_score, 'score' => $this->score, 'moves' => $this->moves);
			} else {
				return false;
			}
		} elseif($text == $this->location) {
			return array('type' => 'location_info', 'text' => $text);
		} else {
			return array('type' => 'text', 'text' => $text);
		}
	}
	
}

?>
