<?php

use noother\Network\SimpleHTTP;

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
		if(false !== $plain = $this->checkMD5MyAddr($hash)) {
			$url = 'http://md5.my-addr.com';
		} elseif(false !== $plain = $this->checkMD5Decryption($hash)) {
			$url = 'http://md5decryption.com';
		} elseif(false !== $plain = $this->checkMD5OnlineNet($hash)) {
			$url = 'http://md5online.net';
		} elseif(false !== $plain = $this->checkMD5Decrypt($hash)) {
			$url = 'http://www.md5decrypt.org';
		} elseif(false !== $plain = $this->checkMD5OnlineOrg($hash)) {
			$url = 'http://md5online.org';
		}
		
		$result['plain'] = $plain;
		$result['url']   = $url;
		
	return $result;
	}
	
	private function checkMD5MyAddr($hash) {
		$html = SimpleHTTP::POST('http://md5.my-addr.com/md5_decrypt-md5_cracker_online/md5_decoder_tool.php', array('md5' => $hash));
		if($html === false) return false;
		
		if(preg_match('#Hashed string</span>: (.*?)</div>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkMD5Decryption($hash) {
		$html = SimpleHTTP::POST('http://md5decryption.com/', array('hash' => $hash, 'submit' => 'Decrypt It!'));
		if($html === false) return false;
		
		if(preg_match('#Decrypted Text: </b>(.*?)</font>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkMD5OnlineNet($hash) {
		$html = SimpleHTTP::POST('http://md5online.net/', array('pass' => $hash, 'option' => 'hash2text'));
		if($html === false) return false;
		
		if(preg_match('#<br>pass : <b>(.*?)</b>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkMD5OnlineOrg($hash) {
		$html = SimpleHTTP::POST('http://md5online.org/', array('md5' => $hash, 'action' => 'decrypt', 'a' => '123456')); // csrf token is not validated
		if($html === false) return false;
		
		if(preg_match('#Found : <b>(.+?)</b>#', $html, $arr)) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	private function checkMD5Decrypt($hash) {
		$HTTP = new HTTP('www.md5decrypt.org');
		$html = $HTTP->GET('/');
		if($html === false) return false;
		if(!preg_match('/<script>document.cookie=\'(.+?)=(.+?)\';/', $html, $arr)) return false;
		
		$HTTP->setCookie($arr[1], $arr[2]);
		
		$res = $HTTP->POST('/index/process', array('value' => base64_encode($hash), 'operation' => 'MD5D'));
		if($res === false) return false;
		
		$json = json_decode($res);
		if(!$json) return false;
		if(!empty($json->error)) return false;
		
	return $json->body;
	}
	
}

?>
