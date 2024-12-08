<?php

namespace noother\Api;

use noother\Cache\FilesystemCache;
use noother\Network\HTTP;

class HackTheBoxApi extends RestApiClient {
	private const API_URL = 'https://labs.hackthebox.com/api';

	private $Cache;

	public function __construct(string $bearer_token) {
		parent::__construct(self::API_URL);

		$this->checkTokenExpire($bearer_token);
		$this->setBearerToken($bearer_token);
	}

	public function getActiveMachines(): array {
		return $this->getAll('/v4/machine/paginated?per_page=100');
	}

	public function getRetiredMachines(): array {
		return $this->getAll('/v4/machine/list/retired/paginated?per_page=100');
	}

	public function getChallenges(): array {
		return $this->getAll('/v4/challenges?per_page=100');
	}

	public function getSherlocks(): array {
		return $this->getAll('/v4/sherlocks?per_page=100');
	}

	private function getAll(string $path, int $page=1): array {
		$res = $this->get($path."&page=$page");

		if($res['meta']['current_page'] < $res['meta']['last_page']) {
			return [...$res['data'], ...$this->getAll($path, $page+1)];
		}

		return $res['data'];
	}

	protected function responseContainsError($res): bool {
		return isset($res['error']);
	}

	private function checkTokenExpire(string $token): void {
		$tmp = explode('.', $token);
		$token = json_decode(base64_decode($tmp[1]));

		if($token->exp < time()-60) {
			throw new \Exception('Token is expired');
		} elseif($token->exp < time()+7*24*60*60) {
			trigger_error('Token is about to expire on '.date('Y-m-d H:i:s', (int)$token->exp), E_USER_WARNING);
		}
	}
}
