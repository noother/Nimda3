<?php

class Mathchall {
	
	private $HTTP;
	
	function __construct() {
		$this->HTTP = new HTTP('www.mathchall.com');
	}
	
	function getChalls() {
		$challs = array();
		$challs = array_merge($challs, $this->getChallsSub('elementary'));
		$challs = array_merge($challs, $this->getChallsSub('middle'));
		$challs = array_merge($challs, $this->getChallsSub('advanced'));
		$challs = array_merge($challs, $this->getChallsSub('university'));
		
	return $challs;
	}
	
	function getChallsSub($sub) {
		$res = $this->HTTP->GET('/en/'.$sub);
		
		preg_match_all('#<a href="(index\.php\?page=challenge.+?id=(\d+?))">(?:<b> )?(.+?)(?:</b> )?</a>.+?<td>(\d+)</td>#s', $res, $arr);
		
		$challs = array();
		for($i=0;$i<sizeof($arr[0]);$i++) {
			$challs[] = array(
				'id' => (int)$arr[2][$i],
				'name' => $arr[3][$i],
				'category' => ucfirst($sub),
				'points' => (int)$arr[4][$i],
				'url' => 'http://www.mathchall.com/en/'.html_entity_decode($arr[1][$i])
			);
		}
		
	return $challs;
	}
	
}

?>
