<?php

class CorePlugin_JoinChannels extends Plugin {
	
	function onConnect() {
		$channels = $this->MySQL->query("SELECT * FROM server_channels WHERE server_id=".$this->Server->serverID." AND active=1");
		
		foreach($channels['result'] as $channel) {
			$this->Server->joinChannel(
				$channel['channel'],
				!empty($channel['key']) ? $channel['key'] : false
			);
		}
	}
	
}

?>
