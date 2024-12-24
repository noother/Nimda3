<?php

namespace noother\ChallengeSite;

use noother\Api\HackTheBoxApi;

class HackTheBox extends ChallengeSite {
	protected const DOMAIN = 'app.hackthebox.com';
	protected const SANITYCHECK_MIN_CHALLS = 1000;

	private $Api;

	protected function login(string $username, string $password): void {
		$this->Api = new HackTheBoxApi($password);
	}

	public function doGetChallenges(): array {
		return [
			...$this->getActiveMachines(),
			...$this->getRetiredMachines(),
			...$this->getChalls(),
			...$this->getSherlocks(),
		];
	}

	private function getActiveMachines(): array {
		$challs = $this->getMachineArray($this->Api->getActiveMachines());
		return array_map(fn($m) => $m+['category' => 'Active Machines'], $challs);
	}

	private function getRetiredMachines(): array {
		$challs = $this->getMachineArray($this->Api->getRetiredMachines());
		return array_map(fn($m) => $m+['category' => 'Retired Machines'], $challs);
	}

	private function getChalls(): array {
		$challs = [];
		foreach($this->Api->getChallenges() as $item) {
			$challs[] = [
				'name'      => $item['name'],
				'url'       => 'https://'.self::DOMAIN.'/challenges/'.$this->htbUrlencode($item['name']),
				'rating'    => $item['difficulty'],
				'category'  => 'Challenges => '.$item['category_name'],
				'solves'    => $item['solves'],
				'is_solved' => $item['is_owned'],
			];
		}

		return $challs;
	}

	private function getSherlocks(): array {
		$challs = [];
		foreach($this->Api->getSherlocks() as $item) {
			$challs[] = [
				'name'      => $item['name'],
				'url'       => 'https://'.self::DOMAIN.'/sherlocks/'.$this->htbUrlencode($item['name']),
				'rating'    => $item['difficulty'],
				'category'  => 'Sherlock => '.$item['category_name'],
				'solves'    => $item['solves'],
				'is_solved' => $item['is_owned'],
			];
		}

		return $challs;
	}

	private function getMachineArray(array $res): array {
		$challs = [];
		foreach($res as $item) {
			$challs[] = [
				'name'      => $item['name'],
				'url'       => 'https://'.self::DOMAIN.'/machines/'.$this->htbUrlencode($item['name']),
				'rating'    => $item['difficultyText'],
				'points'    => $item['static_points'],
				'solves'    => $item['root_owns_count'],
				'is_solved' => $item['authUserInRootOwns'],
			];
		}

		return $challs;
	}

	private function htbUrlencode(string $string): string {
		/**
		 * URLs don't work if urlencoded normally, instead HTB appears to only encode space and it must be encoded with %20 instead of +
		 * Challenges are also double encoded, but it works with single encoding as well
		 */

		return str_replace(' ', '%20', $string);
	}
}
