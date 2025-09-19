<?php

namespace noother\ChallengeSite;

class RootMe extends ChallengeSite {
	protected const DOMAIN = 'www.root-me.org';
	protected const SANITYCHECK_MIN_CHALLS = 580;

	public function doGetChallenges(): array {
		$this->HTTP->set('throttle', 5000); // RootMe gives 429 Too Many Request errors

		$categories = $this->getCategories();
		$challs = [];
		foreach($categories as $category) {
			$challs = [...$challs, ...$this->getChalls($category)];
		}

		return $challs;
	}

	public function getCategories(): array {
		$res = $this->HTTP->GET('/en/Challenges/');
		preg_match_all('#<h4>.+?<a.+?href="(.+?)">(.+?)</a>#', $res, $arr, PREG_SET_ORDER);

		$categories = [];
		foreach($arr as $match) {
			$categories[] = ['url' => '/'.$match[1], 'name' => $match[2]];
		}

		return $categories;
	}

	private function getChalls(array $category): array {
		$res = $this->HTTP->GET($category['url']);

		$challs = [];
		preg_match_all('#<tr>.+?</tr>#s', $res, $arr);
		foreach($arr[0] as $html) {
			preg_match('#squelettes/img/(.+?)\.svg.+?<a href="(.+?)".+?>(.+?)</a>.+?Who \?">(\d+).+?<td>(\d+)</td>.+?title="(.+?) :(?:.+?profil of (.+?)")?#s', $html, $arr);
			$challs[] = [
				'is_solved' => $arr[1] == 'valide',
				'url'       => 'https://'.self::DOMAIN.'/'.$arr[2],
				'name'      => $arr[3],
				'solves'    => (int)$arr[4],
				'points'    => (int)$arr[5],
				'rating'    => $arr[6],
				'author'    => $arr[7] ?? null,
				'category'  => $category['name']
			];
		}

		return $challs;
	}

	protected function login(string $username, string $password): void {
		$res = $this->HTTP->POST('/?page=login&lang=en&ajah=1', ['triggerAjaxLog' => '']);
		preg_match('#<input name=\'formulaire_action_args\' type=\'hidden\'\s*value=\'(.+?)\'#s', $res, $arr);
		$csrf = $arr[1];

		$res = $this->HTTP->POST('/?page=login&lang=en&ajah=1', [
			'var_ajax' => 'form',
			'lang' => 'en',
			'formulaire_action' => 'login',
			'formulaire_action_args' => $csrf,
			'var_login' => $username,
			'password' => $password
		]);
	}
}
