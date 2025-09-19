<?php

namespace noother\ChallengeSite;

class HackMyVM extends ChallengeSite {
	protected const DOMAIN = 'hackmyvm.eu';
	protected const SANITYCHECK_MIN_CHALLS = 350;

	protected function login(string $username, string $password): void {
		$this->HTTP->POST('/login/auth.php', ['admin' => $username, 'password_usuario' => $password]);
	}

	public function doGetChallenges(): array {
		return [...$this->getMachines(), ...$this->getChalls()];
	}

	private function getMachines(int $page=1) {
		$url = '/machines/?l=all';

		$res = $this->HTTP->GET($url);

		preg_match('#<table.+?<tbody>(.+?)</tbody>#s', $res, $arr);
		preg_match_all('#<div class="d-flex.+#', $arr[1], $arr);

		$challs = [];
		foreach($arr[0] as $html) {
			preg_match("#solid \#(.+?);.+?<h4 class='vmname'><a href='(.+?)'>(.+?)</a></h4>.+?<a class=\"mt-2 creator.*?>(.+?)</a>#", $html, $arr2);

			$chall = [
				'category' => 'Machine',
				'rating' => match($arr2[1]) { '28a745' => 'easy', 'ffc107' => 'medium', 'dc3545' => 'hard', default => 'unknown' },
				'url'    => 'https://'.self::DOMAIN.$arr2[2],
				'name'   => $arr2[3],
				'author' => $arr2[4],
			];

			preg_match('#<span class="badge.*?">(.+?)</span>#', $html, $arr2);
			$chall['is_solved'] = $arr2[1] == 'PWNED';

			$challs[] = $chall;
		}

		return $challs;
	}

	private function getChalls(): array {
		$categories = $this->getChallengeCategories();
		$challs = [];
		foreach($categories as $category) {
			$challs = [...$challs, ...$this->getChallengesInCategory($category)];
		}

		return $challs;
	}

	private function getChallengeCategories(): array {
		$res = $this->HTTP->GET('/challenges/');
		preg_match_all('#<a href="cat\.php\?c=(.+?)"#', $res, $arr);

		return $arr[1];
	}

	private function getChallengesInCategory(string $category): array {
		$res = $this->HTTP->GET("/challenges/cat.php?c=$category");
		preg_match_all('#<a href="challenge\.php\?c=(\d+)".+?<span.+?>(.+?)</span>.+?<a href="/profile.+?">(.+?)</a>#s', $res, $arr);

		$challs = [];
		for($i=0;$i<count($arr[0]);$i++) {
			$id = $arr[1][$i];
			$challs[] = [
				'name' => $id,
				'is_solved' => $arr[2][$i] == 'Solved!',
				'author' => $arr[3][$i],
				'category' => ucfirst($category),
				'url' => 'https://'.self::DOMAIN."/challenges/challenge.php?c=$id",
			];
		}

		return $challs;
	}
}
