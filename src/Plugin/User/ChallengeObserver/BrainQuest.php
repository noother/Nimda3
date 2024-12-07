<?php

namespace Nimda\Plugin\User\ChallengeObserver;

use noother\Network\HTTP;

class BrainQuest {
	
	private $username = '';
	private $password = '';
	private $riddleCache = false;
	private $loggedIn = false;
	
	private $HTTP;
	
	function __construct() {
		$this->HTTP = new HTTP('www.brainquest.sk', true);
	}
	
	function login() {
		if(!$this->username || !$this->password) return false;
		
		$this->HTTP->GET('/en/login/');
		$res = $this->HTTP->POST('/login.php', array('txt_login' => $this->username, 'txt_password' => $this->password));
		if(!strstr($res, 'Loged</span> : '.$this->username)) return false;
		
		$this->riddleCache = $res;
		$this->loggedIn = true;
		
	return true;
	}
	
	function getChalls() {
		if(!$this->loggedIn && !$this->login()) return false;
		
		$challs = array();
		$challs = array_merge($challs, $this->getChallsSub('riddles'));
		$challs = array_merge($challs, $this->getChallsSub('beta'));
		$challs = array_merge($challs, $this->getChallsSub('adventures'));
		
	return $challs;
	}
	
	function getChallsSub($sub) {
		if($sub == 'riddles' && $this->riddleCache !== false) {
			$res = $this->riddleCache;
			$this->riddleCache = false;
		} else {
			$res = $this->HTTP->GET('/en/'.$sub);
			if(!$res) return false;
		}
		preg_match_all('#<img src=\'/pic/diamant.+?</a>(.+?)</td>(.+?)(?:<tr class=\'menu_cat2013\'|$)#s', $res, $arr);
		
		$challs = array();
		for($x=0;$x<sizeof($arr[0]);$x++) {
			switch($sub) {
				case 'riddles': case 'adventures': $category = $arr[1][$x]; break;
				case 'beta': $category = $arr[1][$x].' (Beta)'; break;
			}
			
			preg_match_all('#<tr .+?document\.location="(.+?/(\d+))".+?(?:&nbsp;){2}(.+?)</td>#s', $arr[2][$x], $arr2);
			
			for($y=0;$y<sizeof($arr2[0]);$y++) {
				$challs[] = array(
					'id'       => (int)$arr2[2][$y],
					'name'     => $arr2[3][$y],
					'category' => $category,
					'url'      => $arr2[1][$y]
				);
			}
			
		}
		
	return $challs;
	}
	
}

?>
