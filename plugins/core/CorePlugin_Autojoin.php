<?php

class CorePlugin_Autojoin extends Plugin {
	
	function onConnect() {
		$channels = $this->MySQL->query("SELECT `channel`, `key` FROM `autojoin` WHERE `server` = '".$this->Server->host."' AND active=1");
		
		foreach($channels['result'] as $channel) {
			$this->Server->joinChannel(
				$channel['channel'],
				!empty($channel['key']) ? $channel['key'] : false
			);
		}
	}
	
}

?>
