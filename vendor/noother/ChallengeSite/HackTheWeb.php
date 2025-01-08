<?php

namespace noother\ChallengeSite;

class HackTheWeb extends ChallengeSite {
	protected const DOMAIN = 'hack.arrrg.de';
	protected const SANITYCHECK_MIN_CHALLS = 130;

	private const DEMO_USER = 'demo';
	private const DEMO_PASSWORD = 'htw123';

	protected function login(string $username, string $password): void {
		$this->HTTP->POST('/login', ['username' => $username, 'password' => $password]);
	}

	public function doGetChallenges(): array {
		if(!$this->loggedIn) $this->login(self::DEMO_USER, self::DEMO_PASSWORD);

		$res = $this->HTTP->GET('/map');
		preg_match_all('#<a href="(/challenge/\d+).+?fill="(.+?)".+?<text.+?>(.+?)</text>#', $res, $matches, PREG_SET_ORDER);

		$challs = [];
		foreach($matches as $match) {
			$challs[] = [
				'url'       => 'https://'.self::DOMAIN.$match[1],
				'is_solved' => $match[2] != 'var(--success)',
				'name'      => $match[3]
			];
		}

		return $challs;
	}
}
