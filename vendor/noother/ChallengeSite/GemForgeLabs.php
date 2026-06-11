<?php

namespace noother\ChallengeSite;

class GemForgeLabs extends ChallengeSite {
	protected const DOMAIN = 'gemforgelabs.io';
	protected const SANITYCHECK_MIN_CHALLS = 5;

	public function doGetChallenges(): array {
		$res = $this->HTTP->GET('/sitemap.xml');
		preg_match_all('#(/labs/view/slug/.+?)<#', $res, $arr);

		foreach($arr[1] as $url) {
			$challs[] = $this->getChallenge($url);
		}

		return $challs;
	}

	private function getChallenge(string $url): array {
		$chall = ['url' => 'https://'.self::DOMAIN.$url];

		$res = $this->HTTP->GET($url);

		if(preg_match('/<meta name="og:title" content="(.+?)">/', $res, $arr)) {
			$chall['name'] = $arr[1];
		}

		if(preg_match('/<meta name="article:tag" content="(.+?)">/', $res, $arr)) {
			$chall['category'] = rtrim($arr[1], ', ');
		}

		if(preg_match('/<meta name="author" content="(.+?)">/', $res, $arr)) {
			$chall['author'] = $arr[1];
		}

		if(preg_match('#<div.+?title="Score".+?is-warning">(.+?)</#s', $res, $arr)) {
			$chall['points'] = (int)$arr[1];
		}

		if(preg_match('#<div.+?title="Difficulty".+?is-link">(.+?)</#s', $res, $arr)) {
			$chall['rating'] = $arr[1];
		}

		if(preg_match('#<div.+?title="Completions".+?is-link">(\d+?)</#s', $res, $arr)) {
			$chall['solves'] = (int)$arr[1];
		}

		return $chall;
	}
}
