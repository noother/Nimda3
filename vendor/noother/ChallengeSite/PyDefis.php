<?php

namespace noother\ChallengeSite;

class PyDefis extends ChallengeSite {
	protected const DOMAIN = 'pydefis.callicode.fr';
	protected const SANITYCHECK_MIN_CHALLS = 250;

	public function doGetChallenges(): array {
		$res = $this->HTTP->GET('/user/liste_defis');
		preg_match('#<tbody>.+?</tbody>#s', $res, $arr);
		preg_match_all('#<tr>.+?/tr>#s', $arr[0], $arr);
		$blocks = $arr[0];

		$challs = [];
		foreach($blocks as $block) {
			preg_match_all('#<td.*?>(.*?)</td>#s', $block, $arr);
			$cols = $arr[1];
			$is_solved = !empty(trim($cols[0]));

			preg_match('#<a href="(.+?)"#', $cols[3], $arr);
			$url = $arr[1];
			$challs[] = [
				'name'      => html_entity_decode(trim(strip_tags($cols[3]))),
				'is_solved' => $is_solved,
				'solves'    => (int)$cols[4],
				'points'    => (int)$cols[5],
				'url'       => 'https://'.self::DOMAIN.$url
			];
		}

		return $challs;
	}

	protected function login(string $username, string $password): void {
		$this->HTTP->POST('/user/login', ['username' => $username, 'password' => $password, 'page_url' => 'https://pydefis.callicode.fr/']);
	}
}
