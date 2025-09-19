<?php

namespace Nimda\Plugin\Core;

use Nimda\Plugin\Plugin;

class AutojoinPlugin extends Plugin {
	
	function onConnect() {
		$channels = $this->MySQL->query("SELECT `channel`, `key` FROM `server_channels` WHERE `server_id` = '".$this->Server->id."' AND active=1");
		
		foreach($channels as $channel) {
			$this->Server->joinChannel(
				$channel['channel'],
				!empty($channel['key']) ? $channel['key'] : false
			);
		}
	}
	
}

?>
