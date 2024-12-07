<?php

namespace Nimda\Plugin\Core;

use Nimda\Plugin\Plugin;

class RandomNickPlugin extends Plugin {
	
	function onNickAlreadyInUse() {
		$new_nick = $this->data['nick'].'_'.rand(1000,9999);
		$this->Server->setNick($new_nick);
	}
	
}

?>
