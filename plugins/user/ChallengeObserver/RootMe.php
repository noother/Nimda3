<?php

class RootMe {
	
	private $HTTP;
	
	function __construct() {
		$this->HTTP = new HTTP('www.root-me.org');
	}
	
	function getCategories() {
		$html = $this->HTTP->GET('/en/Challenges/');
		preg_match_all('#<div\s*class="filles">.+?<a.+?href="(.+?)">(.+?)</a>#s', $html, $arr);
		
		$categories = array();
		for($i=0;$i<sizeof($arr[0]);$i++) {
			$categories[] = array(
				'name' => $arr[2][$i],
				'path'  => '/'.$arr[1][$i]
			);
		}
		
	return $categories;
	}
	
	function getChalls() {
		$categories = $this->getCategories();
		
		$challs = array();
		foreach($categories as $category) {
			$challs = array_merge($challs, $this->getChallsSub($category));
		}
		
	return $challs;
	}
	
	function getChallsSub($category) {
		$html = $this->HTTP->GET($category['path']);
		preg_match('#<table.+?>.+?</table>#s', $html, $arr);
		$chall_table = $arr[0];
		
		preg_match_all('#<a\s*class="rouge" href="(.+?)".+?>(.+?)</a>.+?(\d+)&nbsp;Points#s', $chall_table, $arr);
		
		$challs = array();
		for($i=0;$i<sizeof($arr[0]);$i++) {
			$challs[] = array(
				'name'     => $arr[2][$i],
				'points'   => $arr[3][$i],
				'category' => $category['name'],
				'url'      => 'http://www.root-me.org/'.$arr[1][$i]
			);
		}
		
	return $challs;
	}
	
}

?>
