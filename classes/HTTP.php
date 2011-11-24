<?php

class HTTP {
	
	private $socket = false;
	private $ip     = false;
	private $host;
	private $port;
	private $cookies   = array();
	private $settings = array(
		'useragent'           => 'Nimda3', // Sets the userAgent to use in the HTTP header
		'auto-follow'         => true,     // If true, header Location's will be followed
		'max-follow'          => 5,        // Follow max this many Location's
		'accept-cookies'      => true,     // Send cookies the server gives us on future requests
		'unstable-connection' => false,    // Try to reconnect until a connection is established
		'connect-timeout'     => 5         // Seconds the connection might take to establish
	);
	
	private $lastHeader = false;
	private $followed = 0;
	
	public function __construct($host, $port=80) {
		$this->host = $host;
		$this->port = $port;
	}
	
	public function GET($path) {
		$this->followed = 0;
		
	return $this->_getResponse('GET', $path);
	}
	
	public function POST($path, $post, $urlencoded=false) {
		$this->followed = 0;
		
		$header = "Content-Type: application/x-www-form-urlencoded\r\n";
		
		$post_string = '';
		foreach($post as $name => $value) {
			$post_string.= ($urlencoded?$name:urlencode($name)).'='.($urlencoded?$value:urlencode($value)).'&';
		}
		$post_string = substr($post_string, 0, -1);
		
		$header.= "Content-Length: ".strlen($post_string)."\r\n\r\n";
		$header.= $post_string;
		
		return $this->_getResponse('POST', $path, $header);
	}
	
	public function set($setting, $value) {
		if(!isset($this->settings[$setting])) return false;
		$this->settings[$setting] = $value;
	}
	
	public function setCookie($name, $value, $urlencoded=false) {
		$this->cookies[$name] = $urlencoded ? $value : urlencode($value);
	}
	
	public function setCookies($array, $urlencoded=false) {
		foreach($array as $name => $value) {
			$this->setCookie($name, $value, $urlencoded);
		}
	}
	
	public function getCookie($name) {
		if(isset($this->cookies[$name])) {
			return $this->cookies[$name];
		} else {
			return false;
		}
	}
	
	public function getCookies() {
		return $this->cookies;
	}
	
	public function removeCookie($name) {
		if(isset($this->cookies[$name])) {
			unset($this->cookies[$name]);
			return true;
		} else {
			return false;
		}
	}
	
	public function clearCookies() {
		$this->cookies = array();
	}
	
	public function getHeader() {
		return $this->lastHeader;
	}
	
	
	private function _connect() {
		if($this->ip === false) $this->ip = gethostbyname($this->host);
		if($this->socket) fclose($this->socket);
		$this->socket = @fsockopen($this->ip, $this->port, $errno, $errstr, $this->settings['connect-timeout']);
		
		if(!$this->socket) {
			if($this->settings['unstable-connection']) {
				usleep(500000);
				return $this->_connect();
			} else {
				trigger_error('Could not connect to '.$this->host.' ('.$this->ip.') on port '.$this->port);
				return false;
			}
		}
		
	return true;
	}
	
	private function _getResponse($method, $path, $additional_headers=null) {
		if($this->socket === false || feof($this->socket)) {
			if(!$this->_connect()) {
				return false;
			}
		}
		
		$header = $method.' '.$path." HTTP/1.1\r\n";
		$header.= "Host: ".$this->host."\r\n";
		$header.= "Accept-Encoding: gzip;q=1, identity;q=0.1\r\n";
		
		if(!empty($this->cookies)) {
			$cookie_string = 'Cookie: ';
			foreach($this->cookies as $name => $value) {
				$cookie_string.= $name.'='.$value.';';
			}
			$cookie_string = substr($cookie_string, 0, -1);
			$header.= $cookie_string."\r\n";
		}
		
		if($this->settings['useragent'] !== false) $header.= "User-Agent: ".$this->settings['useragent']."\r\n";
		
		$header.= "Connection: Keep-Alive\r\n";
		
		if(isset($additional_headers)) {
			$header.= $additional_headers;
		}
		
		$header.= "\r\n";
		fputs($this->socket, $header);
		
		$res = $this->_getHeader();
		if($res === false) return false;
		list($header, $content_length) = $res;
		
		$body = $this->_getBody(
			$content_length,
			(isset($header['Transfer-Encoding']) && $header['Transfer-Encoding'] == 'chunked')
		);
		if(isset($header['Content-Encoding'])) $body = $this->_decodeBody($body, $header['Content-Encoding']);
		
		$this->lastHeader = $header;
		
		if(isset($header['Connection']) && $header['Connection'] == 'close') {
			fclose($this->socket);
			$this->socket = false;
		}
		
		if($this->settings['auto-follow'] && isset($header['Location'])) {
			if(++$this->followed == $this->settings['max-follow']) {
				trigger_error('Server exceeded redirection limit of '.$this->settings['max-follow']);
				return $body;
			}
			
			$parts = parse_url($header['Location']);
			
			$reconnect = false;
			
			if(isset($parts['host']) && $parts['host'] != $this->host) {
				$this->host = $parts['host'];
				$this->clearCookies();
				
				$new_ip = gethostbyname($parts['host']);
				if($new_ip != $this->ip) {
					$reconnect = true;
					$this->ip = $new_ip;
				}
			}
			
			if(isset($parts['port']) && $parts['port'] != $this->port) {
				$this->port = $parts['port'];
				$reconnect = true;
			}
			
			if($reconnect) {
				$this->_connect();
			}
			
			return $this->_getResponse($method, $parts['path'], $additional_headers);
		}
		
		if($this->settings['accept-cookies'] && !empty($header['cookies'])) {
			$this->setCookies($header['cookies'], true);
		}
		
	return $body;
	}
	
	private function _getHeader() {
		$header = array();
		$header['cookies'] = array();
		
		for($c=0; '' !== $line = trim(fgets($this->socket)); $c++) {
			if(!$c) {
				if(!preg_match('#^HTTP/(1\.(?:1|0)) (\d+?) ([\w ]+?)$#', $line, $arr)) {
					return false;
				}
				$header['http_version'] = $arr[1];
				$header['status_code'] = (int)$arr[2];
				$header['status']      = $arr[3];
			} else {
				$tmp = explode(': ', $line, 2);
				if(!isset($tmp[1])) $tmp[1] = '';
				if($tmp[0] == 'Set-Cookie') {
					preg_match('#^(.+?)=(.+?)(:?;|$)#', $tmp[1], $arr);
					$header['cookies'][$arr[1]] = $arr[2];
				} else {
					$header[$tmp[0]] = $tmp[1];
				}
			}
		}
		
		if(!$c) return false; // got no response
		
		if(isset($header['Transfer-Encoding']) && $header['Transfer-Encoding'] == 'chunked') {
			$line = trim(fgets($this->socket));
			preg_match('#^([0-9a-f]+)#', $line, $arr);
			$content_length = hexdec($arr[1]);
		} elseif(isset($header['Content-Length'])) {
			$content_length = $header['Content-Length'];
		} elseif($header['http_version'] == '1.0') {
			$content_length = false;
		} else {
			$content_length = 0;
		}
		
		
	return array($header, $content_length);
	}
	
	private function _getBody($content_length, $chunked) {
		$body = '';
		
		if($content_length === false) { // HTTP 1.0
			while(!feof($this->socket)) {
				$body.= fgets($this->socket);
			}
			return $body;
		} else { // HTTP 1.1
			$received = 0;
			while($received < $content_length) {
				$part = fread($this->socket, $content_length-$received);
				$received+= strlen($part);
				$body.= $part;
			}
	
			if($chunked) {
				fgets($this->socket); // get the \r\n
		
				$next_length = hexdec(trim(fgets($this->socket)));
				if($next_length) {
					return $body.$this->_getBody($next_length, true);
				} else {
					do {
						$line = fgets($this->socket);
					} while($line !== false && $line !== "\r\n");
			
					return $body;
				}
			} else {
				return $body;
			}
		}
	}
	
	private function _decodeBody($body, $encoding) {
		switch($encoding) {
			case 'identity':
				// plain - do nothing
			break;
			case 'gzip':
				// gzdecode() got added in PHP 5.4 - so let's use it, if available
				if(function_exists('gzdecode')) {
					$body = gzdecode($body);
				} else {
					$filename = '/tmp/'.md5(rand()).'.gz';
					file_put_contents($filename, $body);
					$body = shell_exec('gzip -cd '.$filename);
					unlink($filename);
				}
			break;
			default:
				trigger_error('Server sent content with unimplemented encoding "'.$encoding.'"');
			break;
		}
		
	return $body;
	}
	
}

?>
