<?php

namespace noother\ChallengeSite;

class PwnCollege extends ChallengeSite {
	protected const DOMAIN = 'pwn.college';
	protected const SANITYCHECK_MIN_CHALLS = 1400;

	public function doGetChallenges(): array {
		$this->HTTP->set('verbose', true);
		$challs = [];
		foreach($this->getModules() as $module) {
			// Don't go further to not stress the Host. We'll just have to live with only knowing in which module the chall got added
			// Fake challenges to make the list compatible
			for($i=0;$i<$module['count'];$i++) {
				$challs[] = [
					'name' => "Challenge ".($i+1),
					'category' => $module['name'],
					'url' => 'https://'.self::DOMAIN.$module['path']."#challenges-header-".($i+1),
				];
			}
		}

		return $challs;
	}

	public function getModules(): array {
		$modules = [];
		$categories = $this->getCategories();
		foreach($categories as $category) {
			$res = $this->HTTP->GET($category['path']);
			preg_match('#<ul class="card-list">.+?</ul>#s', $res, $arr);
			preg_match_all('#<a.+?href="(.+?)".+?<h4.+?>(.+?)</h4>.+?<br>\d+ / (\d+)#s', $arr[0], $arr2, PREG_SET_ORDER);

			foreach($arr2 as $module) {
				$modules[] = [
					'path'  => $module[1],
					'name'  => $category['name'].' => '.$module[2],
					'count' => (int)$module[3]
				];
			}
		}

		return $modules;
	}

	public function getCategories(): array {
		$res = $this->HTTP->get('/dojos');
		preg_match_all('#<a class="text-decoration-none" href="(/dojo/.+?)".+?<h4 class="card-title">(.+?)</h4>#s', $res, $arr, PREG_SET_ORDER);

		$categories = [];
		foreach($arr as $match) {
			if(str_starts_with($match[1], '/dojo/cse')) continue; // Dupe categories
			$categories[] = [
				'path' => substr($match[1], 5), // Remove "/dojo" as it's just a redirect anyway
				'name' => $match[2],
			];
		}

		return $categories;
	}
}
