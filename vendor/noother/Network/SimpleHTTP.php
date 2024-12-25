<?php

namespace noother\Network;

use noother\Network\HTTP;

class SimpleHTTP {
	public static function GET(string $url): ?string {
		return self::execute($url, 'GET');
	}

	public static function POST(string $url, array $post=null): ?string {
		return self::execute($url, 'POST', $post);
	}

	private static function execute(string $url, string $method, array $post=null): ?string {
		$data = parse_url($url);

		switch($data['scheme']) {
			case 'http':
				$HTTP = new HTTP($data['host'], false, $data['port'] ?? null);
			break;
			case 'https':
				$HTTP = new HTTP($data['host'], true, $data['port'] ?? null);
			break;
			default:
				throw new \Exception("Unsupported protocol: ".$data['scheme']);
		}

		if(isset($data['user']) && isset($data['pass'])) {
			$HTTP->addHeader('Authorization', 'Basic '.base64_encode($data['user'].':'.$data['pass']));
		}

		if(!isset($data['path'])) $data['path'] = '/';
		if(isset($data['query'])) {
			$data['fullpath'] = $data['path'].'?'.$data['query'];
		} else {
			$data['fullpath'] = $data['path'];
		}

		switch($method) {
			case 'GET':
				return $HTTP->GET($data['fullpath']);
			break;
			case 'POST':
				return $HTTP->POST($data['fullpath'], $post);
			break;
			default:
				throw new \Exception("Unsupported method: ".$method);
			break;
		}
	}
}
