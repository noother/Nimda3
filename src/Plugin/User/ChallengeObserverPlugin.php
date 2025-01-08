<?php

namespace Nimda\Plugin\User;

use Nimda\Common;
use Nimda\Configure;
use Nimda\Plugin\Plugin;
use noother\Network\SimpleHTTP;

class ChallengeObserverPlugin extends Plugin {
	private const MAX_ANNOUNCE = 5;
	private const SITES = [
		'BQ'   => ['class' => 'BrainQuest', 'requires_login' => true],
		'HVM'  => ['class' => 'HackMyVM',   'requires_login' => true],
		'HTB'  => ['class' => 'HackTheBox', 'requires_login' => true],
		'PwnC' => ['class' => 'PwnCollege'],
		'PY'   => ['class' => 'PyDefis'],
		'Root' => ['class' => 'RootMe'],
		'WC'   => ['class' => 'WeChall'],
	];

	public $interval = 60;
	public $triggers = ['!latest', '!recent', '!latest_challs', '!recent_challs', '!new_challs', '!update_challs'];
	public $enabledByDefault = false;

	public $helpTriggers = ['!latest'];
	public $helpText = 'Prints the 5 most recent challenges the challenge observer collected. Also it checks periodically if there are new challenges available and prints them in the channel.';
	public $helpCategory = 'Challenges';

	public function onLoad() {
		if(!$this->getVar('sites')) $this->saveVar('sites', $this->getSites());
	}

	public function isTriggered() {
		if($this->data['trigger'] == '!update_challs' && $this->User->nick == Configure::read('master')) { // TODO: need auth
			$this->addJob('getDiff', $this->data['text']);
			return;
		}

		$latest = $this->getVar('latest');
		if(!$latest) return $this->reply('No challenges have been collected yet.');

		$this->reply('Most recent '.count($latest).' challs:');
		foreach($latest as $chall) {
			$this->reply("\x02".$chall['site']."\x02 - ".$chall['text']);
		}
	}

	public function onInterval() {
		$channels = $this->getEnabledChannels();
		if(empty($channels)) return;

		if(
			(date('H', Common::getTime()) == '18' && Common::getTime() > $this->getVar('last_full_update')+60*60) // Trigger a full update every day at 18:00
			|| Common::getTime() > $this->getVar('last_full_update')+24*60*60 // and also trigger it if the 18:xx timeframe was missed for some reason
		) {
			foreach(array_keys(self::SITES) as $site) {
				$this->addJob('getDiff', $site);
			}

			$this->saveVar('last_full_update', time());
			return;
		}

		$new_sites = $this->getSites();
		if(!isset($new_sites)) return;

		$old_sites = $this->getVar('sites');

		foreach($new_sites as $site => $data) {
			if(!isset($old_sites[$site])) {
				$text = sprintf("\x02[Challenges]\x02 A new challenge site just spawned! Checkout \x02%s\x02 at %s.",
					$data['name'],
					$data['url']
				);

				if($data['challs']) {
					$text.= sprintf(" They currently have \x02%d\x02 challenges.",
						$data['challs']
					);
				}

				$this->sendToEnabledChannels($text);
				continue;
			} elseif($data['challs'] != $old_sites[$site]['challs']) {
				$this->addJob('getDiff', $site);
			}
		}

		$this->saveVar('sites', $new_sites);
	}

	public function getDiff(string $site): ?array {
		if(!isset(self::SITES[$site])) return null;

		$class = 'noother\\ChallengeSite\\'.self::SITES[$site]['class'];
		if(self::SITES[$site]['requires_login']??false) {
			$login = Configure::read("logins.$site");
			if(!isset($login) || in_array($login['user'], ['CHANGE_ME']) || in_array($login['password'], ['CHANGE_ME', 'PUT_BEARER_TOKEN'])) return null;
		}

		$ChallengeSite = new $class($login['user']??null, $login['password']??null);

		try {
			return ['site' => $site, 'diff' => $ChallengeSite->getChallengeDiff()];
		} catch(\Exception $e) {
			return ['site' => $site, 'error' => $e->getMessage()];
		}
	}

	public function onJobDone() {
		if(!isset($this->data['result'])) return;

		if(isset($this->data['result']['error'])) {
			$this->sendToEnabledChannels('Error: '.$this->data['result']['site'].': '.$this->data['result']['error']);
			return;
		}

		$site = $this->data['result']['site'];
		$name = $this->getVar('sites')[$site]['name'];
		$diff = $this->data['result']['diff'];

		if(0 < $excess = count($diff['added']) - self::MAX_ANNOUNCE) $diff['added'] = array_slice($diff['added'], 0, self::MAX_ANNOUNCE);
		foreach($diff['added'] as $chall) {
			$text = $this->getAnnouncementText($chall);
			$this->sendToEnabledChannels("\x02[New Challenge] $name -\x02 $text");
			$this->addLatestChall($name, $text);
		}
		if($excess > 0) $this->sendToEnabledChannels("and $excess more");

		if(0 < $excess = count($diff['deleted']) - self::MAX_ANNOUNCE) $diff['deleted'] = array_slice($diff['deleted'], 0, self::MAX_ANNOUNCE);
		foreach($diff['deleted'] as $chall) {
			$text = $this->getAnnouncementText($chall);
			$this->sendToEnabledChannels("\x02[Deleted Challenge] $name -\x02 $text");
		}
		if($excess > 0) $this->sendToEnabledChannels("and $excess more");
	}

	private function getSites(): ?array {
		$sites = [];
		$html = SimpleHTTP::GET('https://www.wechall.net/index.php?mo=WeChall&me=API_Site&no_session=1');
		if(!isset($html)) return null;

		$lines = explode("\n", $html);
		foreach($lines as $line) {
			$line = trim($line);
			$data = explode('::', $line);
			if(count($data) < 11) continue;
			$sites[$data[1]] = ['name' => str_replace('\:', ':', $data[0]), 'url' => str_replace('\:', ':', $data[3]), 'challs' => $data[7]];
		}

		return $sites;
	}

	private function getAnnouncementText(array $chall): string {
		$text = "\x02{$chall['name']}\x02";
		if(isset($chall['category'])) $text.= " in category \x02{$chall['category']}\x02";
		if(isset($chall['author']))   $text.= " by \x02{$chall['author']}\x02";
		if(isset($chall['points']))   $text.= " worth \x02{$chall['points']}\x02 points";
		if(isset($chall['rating']))   $text.= " rated \x02{$chall['rating']}\x02";
		$text.= " ( {$chall['url']} )";

		return $text;
	}

	private function addLatestChall(string $site, string $text): void {
		$latest = $this->getVar('latest', []);

		if(count($latest) >= 5) array_pop($latest);
		array_unshift($latest, ['site' => $site, 'text' => $text]);

		$this->saveVar('latest', $latest);
	}
}
