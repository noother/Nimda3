<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\Network\SimpleHTTP;

class TracePlugin extends Plugin {
	
	public $triggers = array('!trace');
	
	public $helpCategory = 'Internet';
	public $helpText = "Traces an IRC user or a IP/host and gives back it's approximate location.";
	public $usage = '[nick|host|ip]';
	
	private $cloakedRegexps = array(
		'/^\w+\.users\.quakenet\.org$/'
	);
	
	function isTriggered() {
		$isUser = true;
		$host = false;

		if(isset($this->data['text'])) {
			if (strpos($this->data['text'], '.') === false) {
				$target = strtolower($this->data['text']);
				$host = isset($this->Server->users[$target]) ? $this->Server->users[$target]->host : false;
				
				foreach($this->cloakedRegexps as $regex) {
					if(preg_match($regex, $host)) {
						$this->reply("Can't trace ".$target.". Host is cloaked.");
						return;
					}
				}
			} else {
				// if we got a non-nick treat it as IP or host
				$host = $this->data['text'];
				$isUser = false;
			}
		} else {
			// no param, trace current user
			$target = $this->User->nick;
			$host = $this->User->host;
		}

		if ($host === false) {
			$this->reply('I don\'t know this nick.');
			return;
		}

		$html = SimpleHTTP::GET('http://www.geoiptool.com/?IP='.urlencode($host));
		
		preg_match('#IP Address:.*?<td.*?>(.*?)</td>#s', $html, $arr);
		$ip = $arr[1];
		preg_match('#City:.*?<td.*?>(.*?)</td>#s', $html, $arr);
		$city = utf8_encode($arr[1]);
		preg_match('#Country:.*?<td.*?><a.*?>(.*?)</a>#s', $html, $arr);
		$country = utf8_encode(trim($arr[1]));
		preg_match('#Region.*?</td.*?><a.*?>(.*?)</a>#s', $html, $arr);
		$region = utf8_encode($arr[1]);

		if ($isUser) {
			if(empty($city) && empty($region) && empty($country))
				$this->reply('Can\'t trace '.$target.'. Host seems cloaked.');
			else
				$this->reply($target.'\'s location: '.(!empty($city)?$city.', ':'').(!empty($region)?$region.', ':'').$country);
		} else {
			if(empty($city) && empty($region) && empty($country))
				$this->reply('Can\'t trace '.$host.'.');
			else
				$this->reply($this->data['text'].'\'s location: '.(!empty($city)?$city.', ':'').(!empty($region)?$region.', ':'').$country);
		}

	}
	
}

?>
