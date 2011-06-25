<?php

class CorePlugin_RandomNick extends Plugin {
	
	function onNickAlreadyInUse() {
		$new_nick = $this->data['nick'].'_'.rand(1000,9999);
		$this->Server->setNick($new_nick);
	}
	
}

?>
