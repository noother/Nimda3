<?php

namespace noother\Api;

class HackTheBoxApi extends RestApiClient {
	private const API_URL = 'https://labs.hackthebox.com/api';

	public function __construct(string $apitoken) {
		parent::__construct(self::API_URL);

		$this->setBearerToken($apitoken);
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
}
