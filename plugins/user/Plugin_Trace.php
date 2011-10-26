<?php

class Plugin_Trace extends Plugin {
	
	public $triggers = array('!trace');
    
	
	function isTriggered() {
		$isUser = true;
		$host = false;

		if(isset($this->data['text'])) {
			if (strpos($this->data['text'], '.') === false) {
				$target = strtolower($this->data['text']);
				$host = isset($this->Server->users[$target]) ? $this->Server->users[$target]->host : false;
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
			$this->reply('I don\'t know this nick');
			return;
		}

        $html = libHTTP::GET('http://www.geoiptool.com/de/?IP='.urlencode($host));

		$raw = strtr($html, array("\n" => " ", "\r" => " "));
		preg_match('#IP-Addresse:.*?<td.*?>(.*?)</td>#',$raw,$arr);
		$ip = $arr[1];
		preg_match('#Stadt:.*?<td.*?>(.*?)</td>#',$raw,$arr);
		$city = utf8_encode($arr[1]);
		preg_match('#Land:.*?<td.*?><a.*?>(.*?)</a>#',$raw,$arr);
		$country = utf8_encode(trim($arr[1]));
		preg_match('#Region.*?</td.*?><a.*?>(.*?)</a>#',$raw,$arr);
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
