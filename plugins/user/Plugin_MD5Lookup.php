<?php

class Plugin_MD5Lookup extends Plugin {
	
	public $triggers = array('!md5lookup', '!md5crack');
	
	public $helpText = 'Checks various MD5 rainbow tables for your hash and reports back the plaintext if found.';
	public $helpCategory = 'Cryptography';
	public $helpTriggers = array('!md5lookup');
	public $usage = '<md5_hash>';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}
		
		if(!libValidate::md5Hash($this->data['text'])) {
			$this->reply('This is not an MD5 hash.');
			return;
		}
		
		$this->addJob('md5Lookup', $this->data['text']);
	}
	
	function onJobDone() {
		$data = $this->data['result'];
		
		if($data['plain'] === false) {
			$this->reply($this->User->nick.": I can't find a plaintext for your md5 hash ".$data['hash'].'.');
			return;
		}
		
		$this->reply($this->User->nick.': Plaintext for '.$data['hash']." is \x02".$data['plain']."\x02 - found at ".$data['url']);
	}
	
	public function md5Lookup($hash) {
		$result = array('hash' => $hash);
		
		$url = false;
		if(false !== $plain = $this->checkRednoize($hash)) {
			$url = 'http://md5.rednoize.com';
		} elseif(false !== $plain = $this->checkMD5MyAddr($hash)) {
			$url = 'http://md5.my-addr.com';
		} elseif(false !== $plain = $this->checkPasscracking($hash)) {
			$url = 'http://passcracking.com';
		} elseif(false !== $plain = $this->checkHashcrack($hash)) {
			$url = 'http://hashcrack.com';
		} elseif(false !== $plain = $this->checkMD5Decryption($hash)) {
			$url = 'http://md5decryption.com';
		} elseif(false !== $plain = $this->checkMD5net($hash)) {
			$url = 'http://www.md5.net';
		} elseif(false !== $plain = $this->checkMD5Online($hash)) {
			$url = 'http://md5online.net';
		} elseif(false !== $plain = $this->checkMD5Hood($hash)) {
			$url = 'http://md5hood.com';
		} elseif(false !== $plain = $this->checkTMTO($hash)) {
			$url = 'http://www.tmto.org';
		} elseif(false !== $plain = $this->checkEnigmagroup($hash)) {
			$url = 'http://www.enigmagroup.org';
		}
		
		$result['plain'] = $plain;
		$result['url']   = $url;
		
	return $result;
	}
	
	private function checkRednoize($hash) {
		$html = libHTTP::GET('http://md5.rednoize.com/?p&s=md5&q='.$hash.'&_=');
		if($html === false) return false;
		
		if(!empty($html)) {
			return $html;
		} else {
			return false;
		}
	}
	
	private function checkMD5MyAddr($hash) {
		$html = libHTTP::POST('http://md5.my-addr.com/md5_decrypt-md5_cracker_online/md5_decoder_tool.php', array('md5' => $hash));
		if($html === false) return false;
		
		if(preg_match('#Hashed string</span>: (.*?)</div>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkPasscracking($hash) {
		$html = libHTTP::POST('http://passcracking.com/index.php', array('datafromuser' => $hash));
		if($html === false) return false;
		
		if(preg_match('#<td bgcolor=\#FF0000>(.*?)</td>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkHashcrack($hash) {
		$html = libHTTP::POST('http://hashcrack.com/indx.php', array('hash' => $hash, 'auth' => 'fail'));
		if($html === false) return false;
		
		if(preg_match('#<span class=hervorheb2>(.*?)</span>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkMD5Decryption($hash) {
		$html = libHTTP::POST('http://md5decryption.com/', array('hash' => $hash, 'submit' => 'Decrypt It!'));
		if($html === false) return false;
		
		if(preg_match('#Decrypted Text: </b>(.*?)</font>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkMD5net($hash) {
		$html = libHTTP::POST('http://www.md5.net/cracker.php', array('hash' => $hash));
		if($html === false) return false;
		
		if(!preg_match('#<input type="text" id="hash" size="32" value="(.*?)"/>#', $html, $arr)) return false;
		
		if($arr[1] != 'Entry not found.') {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkMD5Online($hash) {
		$html = libHTTP::POST('http://md5online.net/', array('pass' => $hash, 'option' => 'hash2text'));
		if($html === false) return false;
		
		if(preg_match('#<br>pass : <b>(.*?)</b>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkMD5Hood($hash) {
		$html = libHTTP::POST('http://md5hood.com/index.php/cracker/crack', array('md5' => $hash, 'submit' => 'Go'));
		if($html === false) return false;
		
		if(preg_match('#<div class="result_true">(.*?)</div>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkTMTO($hash) {
		$html = libHTTP::GET('http://www.tmto.org/api/latest/?hash='.$hash.'&auth=true');
		if($html === false) return false;
		
		if(!preg_match('#text="(.*?)"#', $html, $arr)) return false;
		
		if(!empty($arr[1])) {
			return base64_decode($arr[1]);
		} else {
			return false;
		}
	}
	
	private function checkEnigmagroup($hash) {
		$HTTP = new HTTP('www.enigmagroup.org');
		$html = $HTTP->GET('/pages/cracker/');
		if($html === false) return false;
		
		preg_match('#<input type="hidden" name="tk" value="(.+?)"#', $html, $arr);
		$token = $arr[1];
		
		$html = $HTTP->POST('/pages/cracker/', array('tk' => $token, 'shash' => $hash, 'type' => 'md5', 'submit_shash' => 'Crack Me'));
		if($html === false) return false;
		
		if(preg_match('#<font color="\#66aa00">MD5: .+? = (.*?)</font>#', $html, $arr)) {
			if($arr[1] == 'Hash not found!') return false;
			else return $arr[1];
		}
		
	return false;
	}
	
}

?>
