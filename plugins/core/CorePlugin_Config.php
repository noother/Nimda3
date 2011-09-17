<?php

class CorePlugin_Config extends Plugin {
	
	public $triggers = array('!config', '!conf');
	
	function isTriggered() {
		if($this->Channel !== false && $this->User->mode != '@') {
			$this->reply('You have to be an operator in this channel to use !config.');
			return;
		}
		
		if(!isset($this->data['text'])) {
			$this->_printUsage();
			return;
		}
		
		$parts = explode(' ', $this->data['text'], 4);
		if(sizeof($parts) < 2) {
			$this->_printUsage();
			return;
		}
		
		$trigger = $parts[0];
		$action  = $parts[1];
		switch($action) {
			case 'list':
				$this->_listConfig($trigger);
			break;
			case 'set':
				if(sizeof($parts) != 4) {
					$this->_printUsage();
					return;
				}
				$this->_setConfig($trigger, $parts[2], $parts[3]);
			break;
			default:
				$this->_printUsage();
			break;
		}
	}
	
	private function _printUsage() {
		$this->reply('Usage: !config !trigger (list)|(set name value)');
	}
	
	private function _listConfig($trigger) {
		$Plugin = $this->_getPlugin($trigger);
		if($Plugin === false) {
			$this->reply('This plugin doesn\'t exist.');
			return;
		}
		
		$config = $Plugin->getConfigList();
		
		list($crap, $plugin_name) = explode('_', get_class($Plugin), 2);
		$this->reply('Available config variables for plugin '.$plugin_name.':');
		
		foreach($config as $name => $def) {
			$output = '%s: %s (%s) - %s';
			
			switch($def['type']) {
				case 'enum':
					$options = '';
					foreach($def['options'] as $option) {
						$options.= $option.', ';
					}
					$options = substr($options, 0, -2);
				break;
				case 'bool':
					$options = 'true, false';
				break;
			}
			var_dump($def['value']);
			$this->reply(sprintf($output,
				$name,
				(is_bool($def['value']) ? ($def['value'] ? 'true' : 'false') : $def['value']),
				$options,
				$def['description']
			));
		}
	}
	
	private function _setConfig($trigger, $name, $value) {
		$Plugin = $this->_getPlugin($trigger);
		if($Plugin === false) {
			$this->reply('This plugin doesn\'t exist.');
			return;
		}
		
		list($crap, $plugin_name) = explode('_', get_class($Plugin), 2);
		
		if($Plugin->setConfig($name, $value)) {
			$this->reply(sprintf(
				"Config variable \x02%s\x02 for plugin \x02%s\x02 has been set to \x02%s\x02 for %s.",
					$name,
					$plugin_name,
					$value,
					($this->Channel === false ? 'user '.$this->User->name : 'channel '.$this->Channel->name.' on '.$this->Server->id)
			));
		} else {
			$all_config = $Plugin->getConfigList();
			if(!isset($all_config[$name])) {
				$this->reply('This config variable doesn\'t exist.');
			} else {
				$this->reply('Please specify a valid value for this config variable.');
			}
		}
	}
	
	private function _getPlugin($trigger) {
		foreach($this->Bot->plugins as $Plugin) {
			if(array_search($trigger, $Plugin->triggers) !== false) {
				$Plugin->User    = $this->User;
				$Plugin->Channel = $this->Channel;
				$Plugin->Server  = $this->Server;
				return $Plugin;
			}
		}
		
	return false;
	}
	
}

?>
