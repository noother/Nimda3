<?php

namespace noother\ChallengeSite;

class HacknRoll extends ChallengeSite {
	protected const DOMAIN = 'hacknroll.dev';
	protected const SANITYCHECK_MIN_CHALLS = 30;

	public function doGetChallenges(): array {
		$html = $this->HTTP->GET('/challenges');

		$categories = $this->getCategories($html);
		$challs = [];
		foreach($categories as $category) {
			$category_challs = $this->getChalls($category['html']);
			$category_challs = array_map(fn($c) => $c + ['category' => $category['name']], $category_challs);

			$challs = [...$challs, ...$category_challs];
		}

		return $challs;
	}

	private function getCategories(string $html): array {
		preg_match_all('#<h5.+?(?=<h5|<script)#s', $html, $arr);
		if(empty($arr[0])) throw new \Exception('Failed getting categories html');

		$categories = [];
		foreach($arr[0] as $category) {
			if(!preg_match('#<h5.+?>(.+?)</h5>#', $category, $arr2)) throw new \Exception('Failed getting category name');
			$categories[] = ['name' => $arr2[1], 'html' => $category];
		}

		return $categories;
	}

	private function getChalls(string $html): array {
		preg_match_all('#<a href="(/challenges/.+?)">(.+?)</a>#s', $html, $arr);
		if(empty($arr[0])) throw new \Exception('Failed getting challenge html');

		$challs = [];
		for($i=0;$i<count($arr[0]);$i++) {
			$url = 'https://'.self::DOMAIN.$arr[1][$i];
			$challenge_html = $arr[2][$i];

			$challs[] = $this->getChall($challenge_html) + ['url' => $url];
		}

		return $challs;
	}

	private function getChall(string $html): array {
		$chall = [];

		if(preg_match('#<h6.+?>(.+?)</h6>#', $html, $arr)) $chall['name'] = html_entity_decode($arr[1]);
		if(preg_match('#<span.+?>(.+?)</span>#', $html, $arr)) $chall['rating'] = $arr[1];
		if(preg_match('#<p.+?>(\d+)#', $html, $arr)) $chall['points'] = (int)$arr[1];
		$chall['is_solved'] = str_contains($html, 'mui-73jy29');

		return $chall;
	}

	protected function login(string $username, string $password): void {
		$this->HTTP->POSTRaw('/api/auth/login', json_encode(['identifier' => $username, 'password' => $password]), 'application/json');
	}
}
