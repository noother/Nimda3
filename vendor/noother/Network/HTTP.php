<?php

namespace noother\Network;

use noother\Debug\Debug;

class HTTP {
	private $socket = false;
	private $ip     = false;
	private $host;
	private $port;
	private $isSSL = false;
	private $cookies   = array();
	private $custom_headers = array();
	private $settings = array(
		'useragent'           => 'Nimda3 (https://github.com/noother/Nimda3)', // Sets the userAgent to use in the HTTP header
		'referer'             => false,    // Sets te referer to use in HTTP header
		'auto-follow'         => true,     // If true, header Location's will be followed
		'max-follow'          => 5,        // Follow max this many Location's
		'accept-cookies'      => true,     // Send cookies the server gives us on future requests
		'xhr'                 => false,    // Make the request look like an XHR request
		'unstable-connection' => false,    // Try to reconnect until a connection is established
		'connect-timeout'     => 5,        // Seconds the connection might take to establish
		'keep-alive'          => true,     // Keep HTTP 1.1 connections alive
		'proxy'               => false,    // Use a proxy (format 1.2.3.4:5678)
		'verify-ssl'          => true,     // Verify SSL certificates
		'verbose'             => false,    // echo all HTTP requests
		'debug'               => false,     // If debug is set to true all HTTP requests come from a local cache directory the second time done instead
		'injections'          => []        // Replace all occurrences of key with value in the final request header
	);

	private $lastHeader = false;
	private $lastHeaderString;
	private $followed = 0;

	public function __construct($host, $ssl=false, $port=null) {
		$this->host = $host;

		if($ssl) {
			if(!isset($port)) $port = 443;
			$this->isSSL = true;
		}

		$this->port = isset($port) ? $port : 80;
		if(defined('HTTP_DEBUG')) {
			$this->set('debug', true);
			$this->set('verbose', true);
		}
	}

	public function GET($path): ?string {
		return $this->request('GET', $path);
	}

	public function POST($path, $post, $urlencoded=false): ?string {
		$header = "Content-Type: application/x-www-form-urlencoded\r\n";

		$post_string = '';
		foreach($post as $name => $value) {
			$post_string.= ($urlencoded?$name:urlencode($name)).'='.($urlencoded?$value:urlencode($value)).'&';
		}
		$post_string = substr($post_string, 0, -1);

		$post_string = $this->applyInjections($post_string);
		$header.= "Content-Length: ".strlen($post_string)."\r\n\r\n";
		$header.= $post_string;

		return $this->request('POST', $path, $header);
	}

	public function POSTRaw(string $path, string $data, string $content_type='text/plain'): ?string {
		$header = "Content-Type: $content_type\r\n";

		$data = $this->applyInjections($data);
		$header.= "Content-Length: ".strlen($data)."\r\n\r\n";
		$header.= $data;

	return $this->request('POST', $path, $header);
	}

	public function PUT($path, $post, $urlencoded=false): ?string {
		$header = "Content-Type: application/x-www-form-urlencoded\r\n";

		$post_string = '';
		foreach($post as $name => $value) {
			$post_string.= ($urlencoded?$name:urlencode($name)).'='.($urlencoded?$value:urlencode($value)).'&';
		}
		$post_string = substr($post_string, 0, -1);

		$post_string = $this->applyInjections($post_string);
		$header.= "Content-Length: ".strlen($post_string)."\r\n\r\n";
		$header.= $post_string;

		return $this->request('PUT', $path, $header);
	}

	public function PUTRaw(string $path, string $data, string $content_type='text/plain'): ?string {
		$header = "Content-Type: $content_type\r\n";

		$data = $this->applyInjections($data);
		$header.= "Content-Length: ".strlen($data)."\r\n\r\n";
		$header.= $data;

	return $this->request('PUT', $path, $header);
	}

	public function DELETE(string $path): ?string {
		return $this->request('DELETE', $path);
	}

	public function request(string $method, string $path, string $headers=null): ?string {
		$this->followed = 0;

		return $this->_getResponse($method, $path, $headers);
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

	public function setHeader(string $name, string $value): void {
		// Overwrite if already exists
		foreach($this->custom_headers as $key => $custom_header) {
			list($header_name, $header_value) = explode(':', $custom_header);
			if($header_name == $name) {
				$this->custom_headers[$key] = "$name: $value\r\n";
				return;
			}
		}

		// If not found, add
		$this->addHeader($name, $value);
	}

	public function addHeader($name, $value): void {
		$this->custom_headers[] = $name.': '.$value."\r\n";
	}

	public function clearCustomHeaders() {
		$this->custom_headers = [];
	}

	public function getHeader($string=false) {
		if($string) return $this->lastHeaderString;
		else return $this->lastHeader;
	}

	private function _connect() {
		if($this->settings['proxy']) {
			list($ip, $port) = explode(':', $this->settings['proxy']);
		} else {
			if($this->ip === false) $this->ip = gethostbyname($this->host);
			$ip = $this->ip;
			$port = $this->port;
		}

		if($this->socket) fclose($this->socket);

		$context = stream_context_create();
		if(!$this->settings['verify-ssl']) {
			stream_context_set_option($context, 'ssl', 'verify_peer', false);
			stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
		}

		$this->socket = @stream_socket_client(($this->isSSL?'ssl://'.$this->host:$ip).':'.$port, $errno, $errstr, $this->settings['connect-timeout'], STREAM_CLIENT_CONNECT, $context);

		if(!$this->socket) {
			if($this->settings['unstable-connection']) {
				usleep(500000);
				return $this->_connect();
			} else {
				trigger_error('Could not connect to '.$this->host.' ('.$ip.') on port '.$port);
				return false;
			}
		}

	return true;
	}

	private function _getResponse($method, $path, $additional_headers=null): ?string {
		if($this->settings['debug']) {
			$normalized_settings = $this->settings;
			$normalized_settings['verbose'] = false;

			$debug_name = $method.'_'.Debug::normalize($this->host.'_'.$path).'_%s_'.md5($method.$path.$additional_headers.implode(',', $this->custom_headers).serialize($normalized_settings).serialize($this->getCookies()));
			$header = Debug::get(sprintf($debug_name, 'header'), true);
			$body   = Debug::get(sprintf($debug_name, 'body'), true);
			if($header !== false && $body !== false) {
				if($this->settings['verbose']) echo '['.$method.'-DBG] '.$path."\n";
				$header = unserialize($header);
				$this->lastHeader = $header;
				if($this->settings['accept-cookies'] && !empty($header['cookies'])) {
					$this->setCookies($header['cookies'], true);
				}
				return $body;
			}
		}

		if($this->socket === false || feof($this->socket)) {
			if(!$this->_connect()) return null;
		}

		if($this->settings['verbose']) echo '['.$method.'] '.$path."\n";

		$header = $method.' '.$path." HTTP/1.1\r\n";
		$header.= "Host: ".$this->host."\r\n";
		$header.= "Accept-Encoding: gzip;q=1, identity;q=0.1\r\n";

		foreach($this->custom_headers as $item) {
			$header.= $item;
		}

		if(!empty($this->cookies)) {
			$cookie_string = 'Cookie: ';
			foreach($this->cookies as $name => $value) {
				$cookie_string.= $name.'='.$value.';';
			}
			$cookie_string = substr($cookie_string, 0, -1);
			$header.= $cookie_string."\r\n";
		}

		if($this->settings['useragent'] !== false) $header.= "User-Agent: ".$this->settings['useragent']."\r\n";
		if($this->settings['referer'] !== false)   $header.= "Referer: ".$this->settings['referer']."\r\n";
		if($this->settings['xhr']) $header.= "X-Requested-With: XMLHttpRequest\r\n";

		$header.= "Connection: ".($this->settings['keep-alive'] ? "Keep-Alive" : "Close" )."\r\n";

		if(isset($additional_headers)) {
			$header.= $additional_headers;
		}

		$header.= "\r\n";
		$header = $this->applyInjections($header);

		fputs($this->socket, $header);

		$res = $this->_getHeader();
		if($res === false) return null;
		list($header, $content_length) = $res;

		$body = $this->_getBody(
			$content_length,
			$this->_getHeaderValue('Transfer-Encoding') === 'chunked'
		);
		if(false !== $encoding = $this->_getHeaderValue('Content-Encoding')) $body = $this->_decodeBody($body, $encoding);

		if($this->_getHeaderValue('Connection') === 'close') {
			fclose($this->socket);
			$this->socket = false;
		}

		if($this->settings['accept-cookies'] && !empty($header['cookies'])) {
			$this->setCookies($header['cookies'], true);
		}

		if($this->settings['auto-follow'] && $this->_getHeaderValue('Location') !== false) {
			if(++$this->followed == $this->settings['max-follow']) {
				trigger_error('Server exceeded redirection limit of '.$this->settings['max-follow']);
				return $body;
			}

			$parts = parse_url($this->_getHeaderValue('Location'));

			$reconnect = false;

			if(isset($parts['host']) && $parts['host'] != $this->host) {
				$this->host = $parts['host'];
				$this->clearCookies();

				$new_ip = gethostbyname($parts['host']);
				if($new_ip != $this->ip || $this->isSSL) {
					$reconnect = true;
					$this->ip = $new_ip;
				}
			}

			if(isset($parts['port']) && $parts['port'] != $this->port) {
				$this->port = $parts['port'];
				$reconnect = true;
			}

			$path = $parts['path'];
			if(isset($parts['query'])) $path.= '?'.$parts['query'];

			if($reconnect) $this->_connect();

			return $this->_getResponse($method, $path, $additional_headers);
		}

		if($this->settings['debug']) {
			Debug::put(sprintf($debug_name, 'header'),       serialize($this->lastHeader), true);
			Debug::put(sprintf($debug_name, 'header_plain'), $this->getHeader('plain'), true);
			Debug::put(sprintf($debug_name, 'body'),         $body, true);
		}

	return $body;
	}

	private function _getHeader() {
		$header = array();
		$header['cookies'] = array();

		$string_header = "";
		for($c=0; '' !== $line = trim(fgets($this->socket)); $c++) {
			$string_header.= $line."\r\n";
			if(!$c) {
				if(!preg_match('#^HTTP/(1\.(?:1|0)) (\d+?)(?: ([\w ]+?))?$#', $line, $arr)) {
					return false;
				}
				$header['http_version'] = $arr[1];
				$header['status_code'] = (int)$arr[2];
				$header['status']      = $arr[3] ?? null;
			} else {
				$tmp = explode(': ', $line, 2);
				if(!isset($tmp[1])) $tmp[1] = '';
				if($tmp[0] == 'Set-Cookie') {
					preg_match('#^(.+?)=(.+?)(:?;|$)#', $tmp[1], $arr);
					$header['cookies'][$arr[1]] = $arr[2];
				} else {
					$header[$tmp[0]] = trim($tmp[1]);
				}
			}
		}

		if(!$c) return false; // got no response

		$this->lastHeader = $header;
		$this->lastHeaderString = $string_header;

		if($this->_getHeaderValue('Transfer-Encoding') === 'chunked') {
			$line = trim(fgets($this->socket));
			preg_match('#^([0-9a-f]+)#i', $line, $arr);
			$content_length = hexdec($arr[1]);
		} elseif(false !== $content_length = $this->_getHeaderValue('Content-Length')) {
			// $content_length = $content_length;
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

				if($content_length != 0) {
					$next_length = hexdec(trim(fgets($this->socket)));
					if($next_length) {
						return $body.$this->_getBody($next_length, true);
					} else {
						do {
							$line = fgets($this->socket);
						} while($line !== false && $line !== "\r\n");

						return $body;
					}
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
					$filename = sys_get_temp_dir().'/'.md5(rand()).'.gz';
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

	private function _getHeaderValue($name) {
		$name = strtolower($name);
		$header = array_change_key_case($this->lastHeader, CASE_LOWER);
		if(!isset($header[$name])) return false;

	return $header[$name];
	}

	private function applyInjections(string $header): string {
		foreach($this->settings['injections'] as $key => $value) {
			$header = str_replace($key, $value, $header);
		}

	return $header;
	}
}
