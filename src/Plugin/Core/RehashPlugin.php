<?php

namespace Nimda\Plugin\Core;

use Nimda\Configure;
use Nimda\Plugin\Plugin;

class RehashPlugin extends Plugin {
	public $triggers = array('!rehash', '!reload');
	public $usage = '<plugin_name|trigger>';
	public $helpText = 'Reload the code of a plugin by doing magic';
	public $hideFromHelp = true;

	public function isTriggered() {
		if($this->User->nick != Configure::read('master')) { // TODO: need auth
			$this->reply('You have to be a '.Configure::read('master').' to use this command');
			return;
		}

		$Plugin = $this->findPlugin($this->data['text']);
		if(!$Plugin) return $this->reply("A plugin with this name doesn't exist");

		$Plugin->reload();

		$this->reply("Rehashed plugin \x02".$Plugin->getName()."\x02");
	}
}
