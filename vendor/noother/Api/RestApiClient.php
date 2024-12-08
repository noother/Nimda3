<?php

namespace noother\Api;

use noother\Network\HTTP;

class RestApiClient {
	private $HTTP;
	private $baseurl;
	private $lastHeader;

	protected $outFormat = 'array';

	public function __construct(string $baseurl) {
		$details = parse_url($baseurl);
		$this->HTTP = new HTTP($details['host'], $details['scheme'] == 'https', $details['port']??443);
		$this->set('auto-follow', false);
		$this->setHeader('Accept', 'application/json');

		$this->baseurl = $details['path'] ?? '';
		// Remove trailing / if given
		if(substr($this->baseurl, -1) == '/') $this->baseurl = substr($this->baseurl, 0, -1);
	}

	public function set($key, $value=null): void {
		if(is_array($key)) {
			foreach($key as $subkey => $value) {
				$this->set($subkey, $value);
			}
			return;
		}

		switch($key) {
			default:
				$this->HTTP->set($key, $value);
		}
	}

	public function setHeader(string $key, string $value): void {
		$this->HTTP->setHeader($key, $value);
	}

	public function getHeader(string $key=null) {
		$headers = $this->HTTP->getHeader();
		if(isset($key)) return $headers[$key] ?? null;

	return $headers;
	}

	public function setBasicAuth(string $auth): void {
		$this->setHeader('Authorization', "Basic ".base64_encode($auth));
	}

	public function setBearerToken(string $token): void {
		$this->setHeader('Authorization', "Bearer $token");
	}


	public function get(string $url) {
		return $this->request('GET', $url);
	}

	public function post(string $url, array $data) {
		return $this->request('POST', $url, $data);
	}

	public function put(string $url, array $data) {
		return $this->request('PUT', $url, $data);
	}

	public function delete(string $url) {
		return $this->request('DELETE', $url);
	}

	/**
	 * @return array|\stdclass Depending on $outFormat property & response json
	 */
	public function request(string $method, string $url, array $data=[]) {
		// Prepend / if not given
		if(substr($url, 0, 1) != '/') $url = "/$url";
		$url = $this->baseurl.$url;

		switch($method) {
			case 'GET':
				$res = $this->HTTP->GET($url);
			break;
			case 'POST':
				$res = $this->HTTP->POSTRaw($url, json_encode($data), 'application/json');
			break;
			case 'PUT':
				$res = $this->HTTP->PUTRaw($url, json_encode($data), 'application/json');
			break;
			case 'DELETE':
				$res = $this->HTTP->DELETE($url);
			break;
			default:
				throw new \Exception("Unknown method '$method'");
		}

		$json = json_decode($res, $this->outFormat == 'array');
		if(!isset($json)) {
			throw new \Exception("Not a json response:\n".print_r($this->HTTP->getHeader(), true)."\n$res");
		}

		if($this->responseContainsError($json)) throw new \Exception("The API response contained an error:\n".json_encode(['request' => ['path' => "$method $url", 'data' => $data], 'response' => $json], JSON_PRETTY_PRINT));

	return $json;
	}

	protected function responseContainsError($json): bool {
		// Override this with custom logic
		return false;
	}

	// Print string, but only if in CLI mode
	protected function out(string $string=''): void {
		if(PHP_SAPI != 'cli') return;

		$class = substr(static::class, strrpos(static::class, '\\')+1);

		echo "$class: $string\n";
	}
}
