<?php

namespace noother\ChallengeSite;

use noother\Api\RestApiClient;

class GemForgeLabs extends ChallengeSite {
	protected const DOMAIN = 'gemforgelabs.io';
	protected const SANITYCHECK_MIN_CHALLS = 10;

	public function doGetChallenges(): array {
		$Api = new RestApiClient('https://'.self::DOMAIN.'/api');

		$challs = [];
		foreach($Api->get('/v1/labs/all') as $chall) {
			$challs[] = [
				'url'      => $chall['url'],
				'name'     => $chall['name'],
				'category' => implode(', ', $chall['tags']),
				'author'   => implode(', ', array_column($chall['authors'], 'user')),
				'points'   => $chall['score'],
				'rating'   => $chall['difficulty'],
				'solves'   => $chall['solves'],
			];
		}

		return $challs;
	}
}
