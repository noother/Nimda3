<?php

namespace noother\ChallengeSite;

use noother\Cache\FilesystemCache;
use noother\Network\HTTP;
use noother\Library\Arrays;

abstract class ChallengeSite {
	protected const DOMAIN = null;
	protected const SANITYCHECK_MIN_CHALLS = 5;

	protected $HTTP;
	protected $debug = false;

	private $Cache;
	private $username;
	private $password;
	private $loggedIn = false;

	public function __construct(string $username=null, string $password=null) {
		$this->HTTP = new HTTP(static::DOMAIN, true);
		$this->HTTP->set('debug', $this->debug);
		$this->HTTP->set('auto-follow', false);

		$this->username = $username;
		$this->password = $password;

		$this->Cache = new FilesystemCache('challengesite_'.(new \ReflectionClass($this))->getShortName());
	}

	/**
	 * Must return an array of challenges, with at least the 'url' field set
	 */
	abstract protected function doGetChallenges(): array;

	/**
	 * Override if needed
	 */
	protected function login(string $username, string $password): void {}

	public function getChallenges(): array {
		if(!$this->loggedIn && isset($this->username, $this->password)) $this->login($this->username, $this->password);

		$challs = $this->doGetChallenges();
		if(count($challs) < static::SANITYCHECK_MIN_CHALLS) throw new \Exception("Sanity check failed, got ".count($challs).' challenges instead of minimum '.static::SANITYCHECK_MIN_CHALLS);

		return $challs;
	}

	public function getChallengeDiff(): array {
		$old_challs = $this->Cache->get('challs');
		$new_challs = $this->getChallenges();
		$this->Cache->put('challs', $new_challs);

		if(!isset($old_challs)) return ['added' => [], 'deleted' => []];

		$old_challs = Arrays::hashtable($old_challs, 'url');
		$new_challs = Arrays::hashtable($new_challs, 'url');

		return [
			'added'   => array_values(array_diff_key($new_challs, $old_challs)),
			'deleted' => array_values(array_diff_key($old_challs, $new_challs))
		];
	}
}
