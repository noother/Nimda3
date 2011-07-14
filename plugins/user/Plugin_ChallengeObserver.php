<?php

class Plugin_ChallengeObserver extends Plugin {
	
	public $interval = 60;
	
	private $channel = '#nimda';
	private $ChallChannel;
	
	function onLoad() {
		if(!$this->getVar('sites')) $this->saveVar('sites', serialize($this->getSites()));
		
		/*
		$sites = $this->getSites();
		$sites['WC']['challs']--;
		$this->saveVar('sites', serialize($sites));
		*/
	}
	
	function onInterval() {
		$new_sites = $this->getSites();
		if(!$new_sites) return;
		
		$old_sites = unserialize($this->getVar('sites'));
		
		$Channel = $this->Bot->servers['freenode']->channels[$this->channel];
		
		foreach($new_sites as $site => $data) {
			
			if(!isset($old_sites[$site])) {
				$Channel->privmsg(sprintf("\x02[Challenges]\x02 A new challenge site just spawned! Checkout \x02%s\x02 at %s. They currently have \x02%d\x02 challenges.",
					$data['name'],
					$data['url'],
					$data['challs']
				));
				continue;
			}
			
			if($data['challs'] < $old_sites[$site]['challs']) {
				$Channel->privmsg(sprintf("\x02[Challenges]\x02 \x02%s\x02 (%s) just deleted \x02%d\x02 of their challenges.",
					$data['name'],
					$data['url'],
					$old_sites['challs']-$data['challs']
				));
				continue;
			}
			
			if($data['challs'] > $old_sites[$site]['challs']) {
				$new_challs = $data['challs']-$old_sites[$site]['challs'];
				$Channel->privmsg(sprintf("\x02[Challenges]\x02 There %s \x02%d\x02 new %s at \x02%s\x02 (%s)",
					$new_challs == 1 ? 'is' : 'are',
					$new_challs,
					$new_challs == 1 ? 'challenge' : 'challenges',
					$data['name'],
					$data['url']
				));
				
				$this->ChallChannel = $Channel;
				$this->getLatestChalls($data, $new_challs);
			}
		}
		
		$this->saveVar('sites', serialize($new_sites));
	}
	
	private function getSites() {
		$sites = array();
		$res = libHTTP::GET('www.wechall.net','/index.php?mo=WeChall&me=API_Site&no_session=1', null, 2);
		if(!$res) return false;
		
		foreach($res['content'] as $line) {
			$data = explode('::',$line);
			if(sizeof($data) < 11) return false;
			$sites[$data[1]] = array('name' => str_replace('\:',':',$data[0]), 'url' => str_replace('\:',':',$data[3]), 'challs' => $data[7]);
		}
		
	return $sites;
	}
	
	private function getLatestChalls($site, $challcount) {
		switch($site['name']) {
			case 'Happy-Security':
				$this->getHappySecurityChalls($challcount);
			break;
			case 'WeChall':
				$this->getWeChallChalls($challcount);
			break;
		}
	}
	
	private function getHappySecurityChalls($count) {
		$res = libHTTP::GET('www.happy-security.de', '/', null, 2);
		if(!$res) return;
		
		if(!preg_match_all('#http://happy-security.de/images/next.gif.*?<a href="(.*?)".*?>(.*?)<.*?\[ (.*?) \].*?>(.*?)<#', $res['raw'], $arr)) {
			return;
		}
		
		for($i=0;$i<$count&&$i<sizeof($arr[0]);$i++) {
			$url      = html_entity_decode($arr[1][$i]);
			$chall    = html_entity_decode($arr[2][$i]);
			$category = html_entity_decode($arr[3][$i]);
			$author   = html_entity_decode($arr[4][$i]);
			
			
			$this->Channel->privmsg(sprintf("\x02%s\x02 in \x02%s\x02 by \x02%s\x02 (%s)",
				$chall,
				$category,
				$author,
				$url
			));
		}
	}
	
	private function getWeChallChalls($count) {
		$res = libHTTP::GET('www.wechall.net', '/challs/by/chall_date/DESC/page-1', null, 2);
		if(!$res) return;
		
		if(!preg_match_all('#<a href="(/challenge/.*?)".*?>(.*?)<.*?href=.*?>(.*?)<#', $res['raw'], $arr)) {
			return;
		}
		
		for($i=0;$i<$count&&$i<sizeof($arr[0]);$i++) {
			$url = 'https://www.wechall.net'.html_entity_decode($arr[1][$i]);
			$chall = html_entity_decode($arr[2][$i]);
			$author = html_entity_decode($arr[3][$i]);
			
			$this->Channel->privmsg(sprintf("\x02%s\x02 by \x02%s\x02 (%s)",
				$chall,
				$author,
				$url
			));
		}
	}	
	
}

?>
