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
			case 'BrainQuest':
				$this->getBrainQuestChalls($challcount);
			break;
			case 'CanYouHack.It':
				$this->getCanYouHackItChalls($challcount);
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
			case 'Rosecode':
				$this->getRosecodeChalls($challcount);
			break;
			case 'Right-Answer':
				$this->getRightAnswerChalls($challcount);
			break;
			/*
			case 'SPOJ':
				$this->getSpojChalls($challcount);
			break;
			*/
			case 'wargame.kr':
				$this->getWargameKrChalls($challcount);
			break;
			case 'W3Challs':
				$this->getW3Challs($challcount);
			break;
			case 'wixxerd.com':
				$this->getWixxerdChalls($challcount);
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
	
	private function getBrainquestChalls($count) {
		require_once('ChallengeObserver/BrainQuest.php');
		
		$BrainQuest = new BrainQuest;
		$challs = $BrainQuest->getChalls();
		if(!$challs) {
			trigger_error('Error while checking Brainquest challs', E_USER_NOTICE);
			return;
		}
		
		if(!$this->getVar('brainquest_challs')) {
			$this->saveVar('brainquest_challs', $challs);
			return;
		}
		
		$result = $this->compareLists($this->getVar('brainquest_challs'), $challs, 'id');
		
		for($i=0;$i<$count&&$i<sizeof($result);$i++) {
			$text = sprintf("\x02%s\x02 in category \x02%s\x02 ( %s )",
				$result[$i]['name'],
				$result[$i]['category'],
				$result[$i]['url']
			);
			
			$this->sendToEnabledChannels($text);
			$this->addLatestChall('BrainQuest', $text);
		}
	}
	
	private function getCanYouHackItChalls($count) {
		$html = libHTTP::GET('http://canyouhack.it/');
		
		preg_match('#<div id="tabs-3">.+?<ul.+?>(.+?)</ul>.+?</div>#s', $html, $arr);
		preg_match_all('#<li>.+?<span.+?</span>(.+?)</a>.+?<a href="(.+?)">(.+?)</a>.+?</li>#', $arr[1], $arr);
		
		for($i=0;$i<$count&&$i<sizeof($arr[0]);$i++) {
			$author = $arr[1][$i];
			
			$pos = strrpos($arr[2][$i], '/');
			$url = 'http://canyouhack.it'.substr($arr[2][$i], 0, $pos+1).rawurlencode(substr($arr[2][$i], $pos+1));
			
			$chall = $arr[3][$i];
			
			$text = sprintf("\x02%s\x02 by \x02%s\x02 ( %s )",
				$chall,
				$author,
				$url
			);
			
			$this->sendToEnabledChannels($text);
			$this->addLatestChall('CanYouHack.It', $text);
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
	
	/*
	private function getSpojChalls($count) {
		
		$this->sendToEnabledChannels("See http://feeds.feedburner.com/SphereOnlineJudge?format=html for a list.");
		return;
		
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
		
		// Only show 5 challs for SPOJ, because due to their quantitiy the spam disadvantage overweights their interestingness
		$show_count = $count;
		if($show_count > 5) $show_count = 5;
		
		for($i=0;$i<$show_count&&$i<sizeof($items);$i++) {
			$html = (string)$items[$i];
			if(!preg_match('#Problem (.+?) \((\d+)\. (.+?)\) added by (.+?) is now available in the (.+?) problemset#', $html, $arr)) {
				$show_count++;
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
		
		if($count > $show_count) {
			$this->sendToEnabledChannels(sprintf("... and %d more. See http://feeds.feedburner.com/SphereOnlineJudge?format=xml for the full list.",
				$count-$show_count
			));
		}
		
	}
	*/
	
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
		$html = libHTTP::GET('http://www.right-answer.net/?lang=Us');
		if(!$html) return;
		
		preg_match('#<h1>New challenges</h1>.*?<ul.+?>(.+?)</ul>#si', $html, $arr);
		preg_match_all('#<div id="(.+?)">.+?<a.+?href="(.+?)&PHPSESSID.+?">(.+?)<span>.+?Give (\d+?) XP#s', $arr[1], $arr);
		
		for($i=0;$i<$count&&$i<sizeof($arr[1]);$i++) {
			$text = sprintf("\x02%s\x02 in category %s worth %d XP ( %s )",
				utf8_encode($arr[3][$i]),
				utf8_encode($arr[1][$i]),
				$arr[4][$i],
				'http://www.right-answer.net/'.$arr[2][$i]
			);
			$this->sendToEnabledChannels($text);
			$this->addLatestChall('Right-Answer', $text);
		}
	}
	
	private function getRosecodeChalls($count) {
		$html = libHTTP::GET('http://www.javaist.com/rosecode/');
		if(!$html) return;
		preg_match('#<span>New Problems</span></h2>.+?<ul.+?>(.+?)</ul>#s', $html, $tmp);
		preg_match_all('#<a href="(.+?)">(?:<font.+?</font> )?(.+?)</a>#', $tmp[1], $arr);
		
		for($i=0;$i<$count&&$i<sizeof($arr[0]);$i++) {
			$text = sprintf("\x02%s\x02 ( %s )",
				$arr[2][$i],
				'http://www.javaist.com/rosecode/'.$arr[1][$i]
			);
			
			$this->sendToEnabledChannels($text);
			$this->addLatestChall('Rosecode', $text);
		}
	}
	
	private function getW3Challs($count) {
		$HTTP = new HTTP('w3challs.com');
		$html = $HTTP->GET('/');
		if(!$html) return;
		preg_match('#<input type="hidden" name="member_token" value="(.+?)" />#', $html, $arr);
		$token = $arr[1];
		
		$html = $HTTP->POST('/profile/awe', array('changeLanguage' => 'fr', 'member_token' => $token));
		if(!$html) return;
		preg_match_all('#<a href="/challenges/challenge(\d+?)".+?title="(.+?), by (.+?) \(Points: (\d+?);#', $html, $arr);
	
		$challs = array();
		for($i=0;$i<sizeof($arr[1]);$i++) {
			$challs[] = array(
				'id' => $arr[1][$i],
				'name' => $arr[2][$i],
				'author' => $arr[3][$i],
				'points' => $arr[4][$i]
			);
		}
		usort($challs, array('self', 'sortByID'));
		
		for($i=0;$i<$count;$i++) {
			$text = sprintf("\x02%s\x02 by \x02%s\x02 worth %d points ( %s )",
				$challs[$i]['name'],
				$challs[$i]['author'],
				$challs[$i]['points'],
				'http://w3challs.com/challenges/challenge'.$challs[$i]['id']
			);
			$this->sendToEnabledChannels($text);
			$this->addLatestChall('W3Challs', $text);
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
	
	private function getWixxerdChalls($count) {
		$html = libHTTP::GET('http://www.wixxerd.com/challenges/');
		if(!$html) return;
		
		preg_match('#<span.+?>New Challenges</span>(.+?)\s{3,}#', $html, $arr);
		preg_match_all('#<a href="(.+?)">(.+?)</a>#', $arr[1], $arr);
		
		for($i=0;$i<$count&&$i<sizeof($arr[1]);$i++) {
			preg_match('#>'.preg_quote($arr[2][$i]).'</a>\s+?</td>\s+?<td.+?>(.+?)</td>\s+?<td.+?>(.+?)</td>#', $html, $arr2);
			
			$text = sprintf("\x02%s\x02 in category \x02%s\x02 rated \x02%s\x02 ( %s )",
				$arr[2][$i],
				$arr2[1],
				$arr2[2],
				$arr[1][$i]
			);
			$this->sendToEnabledChannels($text);
			$this->addLatestChall('wixxerd.com', $text);
		}
	}
	
	private function sortByID($a, $b) {
		return $a['id'] > $b['id'] ? -1 : 1;
	}
	
	private function compareLists($old_list, $new_list, $unique_key) {
		$result = array();
		
		foreach($new_list as $new_item) {
			foreach($old_list as $key => $old_item) {
				if($new_item[$unique_key] == $old_item[$unique_key]) {
					unset($old_list[$key]); // so we don't have to check this one again on next iterations
					continue 2;
				}
			}
			$result[] = $new_item;
		}
		
	return $result;
	}
	
}

?>
