<?php

class Plugin_ChallengeObserver extends Plugin {
	
	public $interval = 60;
	public $triggers = array('!latest', '!recent', '!latest_challs', '!recent_challs', '!new_challs');
	public $enabledByDefault = false;
	
	public $helpTriggers = array('!latest');
	public $helpText = 'Prints the 5 most recent challenges the challenge observer collected. Also it checks periodically if there are new challenges available and prints them in the channel.';
	public $helpCategory = 'Challenges';
	
	
	function onLoad() {
		if(!$this->getVar('sites'))  $this->saveVar('sites', $this->getSites());
		if(!$this->getVar('latest')) $this->saveVar('latest', array());
		
		/*
		$sites = $this->getSites();
		$sites['Mic']['challs']-=2;
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
		$channels = $this->getEnabledChannels();
		if(empty($channels)) return;
		
		$new_sites = $this->getSites();
		if(!$new_sites) return;
		
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
			} elseif($data['challs'] < $old_sites[$site]['challs']) {
				$this->sendToEnabledChannels(sprintf("\x02[Challenges]\x02 \x02%s\x02 (%s) just deleted \x02%d\x02 of their challenges.",
					$data['name'],
					$data['url'],
					$old_sites[$site]['challs']-$data['challs']
				));
			} elseif($data['challs'] > $old_sites[$site]['challs']) {
				$new_challs = $data['challs']-$old_sites[$site]['challs'];
				$this->sendToEnabledChannels(sprintf("\x02[Challenges]\x02 There %s \x02%d\x02 new %s at \x02%s\x02 ( %s )",
					$new_challs == 1 ? 'is' : 'are',
					$new_challs,
					$new_challs == 1 ? 'challenge' : 'challenges',
					$data['name'],
					$data['url']
				));
				
				$this->getLatestChalls($data, $new_challs);
			}
		}
		
		$this->saveVar('sites', $new_sites);
	}
	
	private function getSites() {
		$sites = array();
		$html = libHTTP::GET('http://www.wechall.net/index.php?mo=WeChall&me=API_Site&no_session=1');
		if($html === false) return false;
		
		$lines = explode("\n", $html);
		
		foreach($lines as $line) {
			$line = trim($line);
			$data = explode('::',$line);
			if(sizeof($data) < 11) continue;
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
			case 'µContest':
				$this->getMicrocontestChalls($challcount);
			break;
			case 'Rankk':
				$this->getRankkChalls($challcount);
			break;
			case 'Revolution Elite':
				$this->getRevolutionEliteChalls($challcount);
			break;
			case 'Right-Answer':
				$this->getRightAnswerChalls($challcount);
			break;
			case 'SPOJ':
				$this->getSpojChalls($challcount);
			break;
			case 'wargame.kr':
				$this->getWargameKrChalls($challcount);
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
		$html = libHTTP::GET('http://www.happy-security.de/index.php?modul=hacking-zone');
		if($html === false) return;
		
		if(!preg_match_all('#<td valign=top nowrap colspan=8> &nbsp;&raquo; <b>(.+?)</b>(.+?)</tr></table>#s', $html, $categories)) {
			return;
		}
		
		$challs = array();
		
		for($i=0;$i<sizeof($categories[1]);$i++) {
			if(!preg_match_all('#<a href="?/?\?modul=hacking-zone&action=showhackit&level_id=(\d+?)"?>(.+?)</b>.+?<b>(.+?)</b>#s', $categories[2][$i], $arr)) {
				continue;
			}
			
			for($j=0;$j<sizeof($arr[1]);$j++) {
				$challs[] = array(
					'id'       => (int)$arr[1][$j],
					'name'     => $arr[2][$j],
					'category' => $categories[1][$i],
					'author'   => $arr[3][$j]
				);
			}
		}
		
		usort($challs, array('self', 'sortByID'));
		
		for($i=0;$i<$count&&$i<sizeof($challs);$i++) {
			$url      = 'http://happy-security.de/c/?x='.$challs[$i]['id'];
			$chall    = $challs[$i]['name'];
			$category = $challs[$i]['category'];
			$author   = $challs[$i]['author'];
			
			$text = sprintf("\x02%s\x02 in \x02%s\x02 by \x02%s\x02 ( %s )",
				$chall,
				$category,
				$author,
				$url
			);
			
			$this->sendToEnabledChannels($text);
			$this->addLatestChall('Happy Security', $text);
		}
	}
	
	private function getWeChallChalls($count) {
		$html = libHTTP::GET('http://www.wechall.net/challs/by/chall_date/DESC/page-1');
		if($html === false) return;
		
		if(!preg_match_all('#<a href="(/challenge/.*?)".*?>(.*?)<.*?href=.*?>(.*?)<#', $html, $arr)) {
			return;
		}
		
		for($i=0;$i<$count&&$i<sizeof($arr[0]);$i++) {
			$url    = 'https://www.wechall.net'.html_entity_decode($arr[1][$i]);
			$chall  = html_entity_decode($arr[2][$i]);
			$author = html_entity_decode($arr[3][$i]);
			
			$text = sprintf("\x02%s\x02 by \x02%s\x02 ( %s )",
				$chall,
				$author,
				$url
			);
			
			$this->sendToEnabledChannels($text);
			
			$this->addLatestChall('WeChall', $text);
		}
	}
	
	private function getMicrocontestChalls($count) {
		$html = libHTTP::GET('http://www.microcontest.com/contests.php?id=-1');
		
		if(!preg_match_all('#<a href="contest.php\?id=(\d+?)">(.+?) \(\d+\)</a>.*?<a href="contests.php.+?>(.*?)</a>.*?<td.+?>(\d+?)</td>#s', $html, $arr)) {
			return;
		}
		
		$challs = array();
		for($i=0;$i<sizeof($arr[1]);$i++) {
				$challs[] = array(
					'id'       => (int)$arr[1][$i],
					'name'     => $arr[2][$i],
					'category' => $arr[3][$i],
					'points'   => $arr[4][$i]
				);
		}
		
		usort($challs, array('self', 'sortByID'));
		
		for($i=0;$i<$count&&$i<sizeof($challs);$i++) {
			$url      = 'http://www.microcontest.com/contest.php?id='.$challs[$i]['id'];
			$chall    = $challs[$i]['name'];
			$category = $challs[$i]['category'];
			$points   = $challs[$i]['points'];
			
			$text = sprintf("\x02%s\x02 in category \x02%s\x02 worth %d points ( %s )",
				$chall,
				$category,
				$points,
				$url
			);
			
			$this->sendToEnabledChannels($text);
			
			$this->addLatestChall('µContest', $text);
		}
		
	}
	
	private function getSpojChalls($count) {
		$html = libHTTP::GET('http://feeds.feedburner.com/SphereOnlineJudge?format=xml');
		if($html === false) return;
		
		// Temporary, till SPOJ fixes their feed
		$tmp = explode("\n", $html);
		$new = '';
		foreach($tmp as $line) {
			if(!libString::endsWith('<br />', $line)) $new.= $line."\n";
		}
		$html = $new;
		
		
		$XML = simplexml_load_string($html);
		if($XML === false) return;
		
		$items = $XML->xpath('channel/item/description');
		
		for($i=0;$i<$count&&$i<sizeof($items);$i++) {
			$html = (string)$items[$i];
			if(!preg_match('#Problem (.+?) \((\d+)\. (.+?)\) added by (.+?) is now available in the (.+?) problemset#', $html, $arr)) {
				$count++;
				continue;
			}
			
			$code     = $arr[1];
			$id       = $arr[2];
			$name     = $arr[3];
			$author   = $arr[4];
			$category = $arr[5];
			$url      = 'http://www.spoj.pl/problems/'.$code.'/';
			
			$text = sprintf("\x02%s\x02 by \x02%s\x02 in the \x02%s\x02 problemset ( %s )",
				$name,
				$author,
				$category,
				$url
			);
			
			$this->sendToEnabledChannels($text);
			
			// Intentionally not adding to the latest challs, because
			// they have just too many and it would flood the normal challs
		}
	}
	
	private function getRankkChalls($count) {
		$html = libHTTP::GET('http://twitter.com/statuses/user_timeline/315747759.rss');
		if($html === false) return;
	
		$XML = simplexml_load_string($html);
		if($XML === false) return;
	
		$items = $XML->xpath('channel/item/title');
	
		for($i=0,$j=0;$i<$count&&$j<sizeof($items);$j++) {
			$string = (string)$items[$j];
		
			if(preg_match('/^rankk_org: Added Challenge (\d+?): (.+?) (.+)$/', $string, $arr)) {
				$i++;
				$num  = $arr[1];
				$id   = $arr[2];
				$name = $arr[3];
			
				$text = sprintf("Challenge %s: \x02%s\x02",
					$id,
					$name
				);
			
				$this->sendToEnabledChannels($text);
				$this->addLatestChall('Rankk', $text);
			}
		}
	}
	
	private function getRevolutionEliteChalls($count) {
		$html = libHTTP::GET('http://sabrefilms.co.uk/revolutionelite/index.php');
		if(!$html) return;
		
		preg_match('#<h5>Latest Challenge Online:</h5><a href="http://sabrefilms.co.uk/revolutionelite/challs.php\?challs=(.+?) *?"> *?(.+?) *?</a>#', $html, $arr);
		
		$chall_name = trim($arr[2]);
		
		$url = strtolower($arr[1]);
		$new_url = '';
		for($i=0;$i<strlen($url);$i++) {
			if(preg_match('/[a-z0-9 ]/', $url{$i})) {
				$new_url.= $url{$i};
			}
		}
		$new_url = str_replace(' ', '-', $new_url);
		$url = 'http://sabrefilms.co.uk/revolutionelite/'.$new_url.'.php';
		
		$text = sprintf("\x02%s\x02 ( %s )",
			$chall_name,
			$url
		);
		
		$this->sendToEnabledChannels($text);
		$this->addLatestChall('Revolution Elite', $text);
	}
	
	private function getRightAnswerChalls($count) {
		$html = libHTTP::GET('http://www.right-answer.net/index.php');
		if(!$html) return;
		
		preg_match_all('#<a.+?Give (\d+?) XP.+?href="epreuves\.php\?nom=(.+?)">(.+?)</a>#s', $html, $arr);
		
		for($i=0;$i<$count&&$i<sizeof($arr[1]);$i++) {
			$text = sprintf("\x02%s\x02 worth %d XP ( %s )",
				utf8_encode($arr[3][$i]),
				$arr[1][$i],
				'http://www.right-answer.net/epreuves.php?nom='.urlencode($arr[2][$i])
			);
			$this->sendToEnabledChannels($text);
			$this->addLatestChall('Right-Answer', $text);
		}
	}
	
	private function getWargameKrChalls($count) {
		$html = libHTTP::GET('http://www.wargame.kr/challenge/regtime/');
		if(!$html) return;
		
		preg_match_all('#<li .+?<a.+?href=\'(.+?)\'.+?<strong>(.+?)</strong>.+?Point : (\d+?) p#', $html, $arr);
		
		for($i=0;$i<$count&&$i<sizeof($arr[1]);$i++) {
			$text = sprintf("\x02%s\x02 worth %d points ( %s )",
				$arr[2][$i],
				$arr[3][$i],
				$arr[1][$i]
			);
			$this->sendToEnabledChannels($text);
			$this->addLatestChall('wargame.kr', $text);
		}
		
	}
	
	private function sortByID($a, $b) {
		return $a['id'] > $b['id'] ? -1 : 1;
	}
	
}

?>
