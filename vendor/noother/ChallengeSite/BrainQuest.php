<?php

namespace noother\ChallengeSite;

class BrainQuest extends ChallengeSite {
	protected const DOMAIN = 'www.bqbi.net';
	protected const SANITYCHECK_MIN_CHALLS = 800;

	public function doGetChallenges(): array {
		$res = $this->HTTP->GET('/en/riddles');
		preg_match_all('#<tr id=\'trm_.+?</tr>#', $res, $arr);

		$challs = [];
		foreach($arr[0] as $data) {
			if(!preg_match('#location="(.+?)"#', $data, $arr)) continue;
			$chall = [];
			$chall['url'] = $arr[1];
			$chall['is_solved'] = strpos($data, '/ok.png') !== false;

			preg_match('#<span id=\'spnDone.+?\'></span>(.+?)</td>#', $data, $arr);
			$chall['name'] = str_replace('&nbsp;', '', strip_tags($arr[1]));

			preg_match('#<span id=\'spnCnt\d+\'>(\d+)#', $data, $arr);
			$chall['solves'] = (int)$arr[1];

			$challs[] = $chall;
		}

		return $challs;
	}

	protected function login(string $username, string $password): void {
		$this->HTTP->POST('/login.php', ['txt_login' => $username, 'txt_password' => $password]);
	}
}
