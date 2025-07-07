<?php

namespace noother\Api;

use noother\Cache\FilesystemCache;
use noother\Network\HTTP;

class HackTheBoxApi extends RestApiClient {
	private const API_URL = 'https://labs.hackthebox.com/api';

	private $Cache;

	public function __construct(string $token_file) {
		parent::__construct(self::API_URL);

		$this->setBearerToken($this->getAccessToken($token_file));
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

	private function getAccessToken(string $token_file): string {
		$token = json_decode(file_get_contents($token_file));

		$tmp = explode('.', $token->access_token);

		$decoded = json_decode(base64_decode($tmp[1]));
		if(!isset($decoded)) throw new \Exception("Invalid access_token in $token_file");

		if($decoded->iat < time()-60*60*11) {
			$this->refreshToken($token_file);
			return $this->getAccessToken($token_file);
		}

		return $token->access_token;
	}

	private function refreshToken(string $token_file): void {
		$token = json_decode(file_get_contents($token_file));
		if(!isset($token->refresh_token)) throw new \Exception("refresh_token unset in $token_file");

		$res = $this->post('/v4/login/refresh', [
			'refresh_token' => $token->refresh_token
		]);

		if(!isset($res['message']['access_token'])) throw new \Exception('Refreshing token failed: '.json_encode($res));

		file_put_contents($token_file, json_encode($res['message'], JSON_PRETTY_PRINT));
	}
}
