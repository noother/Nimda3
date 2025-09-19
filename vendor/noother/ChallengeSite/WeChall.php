<?php

namespace noother\ChallengeSite;

class WeChall extends ChallengeSite {
	protected const DOMAIN = 'www.wechall.net';
	protected const SANITYCHECK_MIN_CHALLS = 150;

	protected function login(string $username, string $password): void {
		$this->HTTP->setCookie('WC', 'i_like_cookies');
		$this->HTTP->POST('/login', ['username' => $username, 'password' => $password, 'login' => 'Login']);
	}

	public function doGetChallenges(): array {
		$res = $this->HTTP->GET('/all_challs/');
		preg_match('#<table class="wc_chall_table">.+?</table>#s', $res, $arr);
		preg_match_all('#<tr class=" gwf_(odd|even)">.+?</tr>#s', $arr[0], $arr);

		$challs = [];
		foreach($arr[0] as $data) {
			preg_match('#<a href="(.+?)" title=".+?" class="wc_chall_solved_(\d)">(.+?)</a> by (.+?)</td>#', $data, $arr);
			$chall = [
				'url'       => 'https://'.self::DOMAIN.$arr[1],
				'is_solved' => (bool)$arr[2],
				'name'      => $arr[3],
				'author'    => strip_tags($arr[4]),
			];

			preg_match('#<a href="/challenge_solvers_for/.+?">(\d+)</a>#', $data, $arr);
			$chall['solves'] = (int)$arr[1];

			preg_match('#<td class="gwf_num">(\d+)</td>#', $data, $arr);
			$chall['points'] = (int)$arr[1];

			$challs[] = $chall;
		}

		return $challs;
	}

	public function getLeaderboard(int $pages=1): ?array {
		$html = '';
		for($page=1;$page<=$pages;$page++) {
			$res = $this->HTTP->GET("/ranking/page-$page");
			if(!$res) return null;
			$html.= $res;
		}

		preg_match_all('#<tr id="rank_(\d+)".+?<a href="/profile/.+?>(.+?)</a>.+?"gwf_num">.+?"gwf_num">(\d+)</td>#s', $html, $arr, PREG_SET_ORDER);
		$leaderboard = [];
		foreach($arr as $item) {
			$leaderboard[] = [
				'rank'   => (int)$item[1],
				'user'   => $item[2],
				'points' => (int)$item[3],
			];
		}

		return $leaderboard;
	}
}
