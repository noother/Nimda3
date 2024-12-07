<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;

class HashPlugin extends Plugin {
	
	public $triggers = array('!hash');
	
	public $helpText = 'Calculates hashes with various alogrithms. There are also shortcuts for the most used algorithms, like !md5, !sha1, etc.';
	public $helpCategory = 'Cryptography';
	public $helpTriggers = array('!hash');
	public $usage = '(<algo> <text>) | algos';
	
	function onLoad() {
		$algos = hash_algos();
		foreach($algos as $algo) {
			if(strstr($algo, ',') === false) {
				$this->triggers[] = '!'.$algo;
			}
		}
	}
	
	function isTriggered() {
		if($this->data['trigger'] == '!hash') {
			if(!isset($this->data['text'])) {
				$this->reply('Usage: !hash <algo> <text> or !hash algos for a list');
				return;
			}
			
			$tmp = explode(' ', $this->data['text'], 2);
			$algo = $tmp[0];
			if(isset($tmp[1])) $text = $tmp[1];
		} else {
			$algo = substr($this->data['trigger'], 1);
			if(isset($this->data['text'])) $text = $this->data['text'];
		}
		
		if($algo == 'algos') {
			/* output list of available algorithms */
			$this->reply('Algos: '.implode('; ', hash_algos()));
		} else {
			if(array_search($algo, hash_algos()) === false) {
				$this->reply('Unknown hash algorithm');
				return;
			}
			
			if(!isset($text)) {
				/* show info about algorithm */
				$x = hash($algo, 'abc', false);
				$len = strlen($x)/2;
				$this->reply(sprintf('Length: %d bit (%d bytes)', $len*8, $len));
			} else {
				/* hash input with algorithm */
				$this->reply('Result: '.hash($algo, $text, false));
			}
		}
	}
	
}

?>
