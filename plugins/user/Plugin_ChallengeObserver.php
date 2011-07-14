<?php

class Plugin_ChallengeObserver extends Plugin {
	
	public $interval = 60;
	public $triggers = array('!latest', '!recent', '!latest_challs', '!recent_challs', '!new_challs');
	
	private $channel = '#nimda';
	private $ChallChannel;
	
	function onLoad() {
		if(!$this->getVar('sites'))  $this->saveVar('sites', $this->getSites());
		if(!$this->getVar('latest')) $this->saveVar('latest', array());
		
		/*
		$sites = $this->getSites();
		$sites['HS']['challs']-=2;
		$this->saveVar('sites', $sites);
		*/
	}
	
	function isTriggered() {
		$latest = $this->getVar('latest');
		if(empty($latest)) {
			$this->reply('No challenges have been collected yet.');
			return;
		}
		
		$this->reply('Most recent '.sizeof($latest).' challs:');
		foreach($latest as $chall) {
			$this->reply("\x02".$chall['site']."\x02 - ".$chall['text']);
		}
	}
	
	function onInterval() {
		$new_sites = $this->getSites();
		if(!$new_sites) return;
		
		$old_sites = $this->getVar('sites');
		
		$Channel = $this->Bot->servers['freenode']->channels[$this->channel];
		
		foreach($new_sites as $site => $data) {
			if(!isset($old_sites[$site])) {
				$Channel->privmsg(sprintf("\x02[Challenges]\x02 A new challenge site just spawned! Checkout \x02%s\x02 at %s. They currently have \x02%d\x02 challenges.",
					$data['name'],
					$data['url'],
					$data['challs']
				));
			} elseif($data['challs'] < $old_sites[$site]['challs']) {
				$Channel->privmsg(sprintf("\x02[Challenges]\x02 \x02%s\x02 (%s) just deleted \x02%d\x02 of their challenges.",
					$data['name'],
					$data['url'],
					$old_sites[$site]['challs']-$data['challs']
				));
			} elseif($data['challs'] > $old_sites[$site]['challs']) {
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
		
		$this->saveVar('sites', $new_sites);
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
	
	private function addLatestChall($site, $text) {
		$latest = $this->getVar('latest');
		
		if(sizeof($latest) >= 5) array_pop($latest);
		array_unshift($latest, array('site' => $site, 'text' => $text));
		
		$this->saveVar('latest', $latest);
	}
	
	private function getHappySecurityChalls($count) {
		$res = libHTTP::GET('www.happy-security.de', '/index.php?modul=hacking-zone', null, 2);
		if(!$res) return;
		
		if(!preg_match_all('#<td valign=top nowrap colspan=8> &nbsp;&raquo; <b>(.+?)</b>(.+?)</tr></table>#s', $res['raw'], $categories)) {
			return;
		}
		
		$challs = array();
		
		for($i=0;$i<sizeof($categories[1]);$i++) {
			if(!preg_match_all('#<a href="?/?\?modul=hacking-zone&action=showhackit&level_id=(\d+?)"?>(.+?)</b>.+?<b>(.+?)</b>#s', $categories[2][$i], $arr)) {
				continue;
			}
			
			for($j=0;$j<sizeof($arr[1]);$j++) {
				$challs[] = array('id' => (int)$arr[1][$j], 'name' => $arr[2][$j], 'category' => $categories[1][$i], 'author' => $arr[3][$j]);
			}
		}
		
		usort($challs, array('self', 'cmpHappySecurityChalls'));
		
		for($i=0;$i<$count&&$i<sizeof($challs);$i++) {
			$url      = 'http://www.happy-security.de/index.php?modul=hacking-zone&action=showhackit&level_id='.$challs[$i]['id'];
			$chall    = $challs[$i]['name'];
			$category = $challs[$i]['category'];
			$author   = $challs[$i]['author'];
			
			$text = sprintf("\x02%s\x02 in \x02%s\x02 by \x02%s\x02 (%s)",
				$chall,
				$category,
				$author,
				$url
			);
			
			$this->ChallChannel->privmsg($text);
			$this->addLatestChall('Happy Security', $text);
		}
	}
	
	private function cmpHappySecurityChalls($a, $b) {
		return $a['id'] > $b['id'] ? -1 : 1;
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
			
			$text = sprintf("\x02%s\x02 by \x02%s\x02 (%s)",
				$chall,
				$author,
				$url
			);
			
			$this->ChallChannel->privmsg($text);
			
			$this->addLatestChall('WeChall', $text);
		}
	}
	
}

?>
