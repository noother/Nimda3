<?php

class CorePlugin_Config extends Plugin {
	
	public $triggers = array('!config', '!conf');
	public $usage = '<!trigger> (list)|(set name value)';
	
	public $helpTriggers = array('!config');
	public $helpText = "Let's you configure the plugin for your channel / yourself (in query). To set channel configuration you have to be an operator in the channel";
	
	function isTriggered() {
		if($this->Channel !== false && $this->User->mode != '@') {
			$this->reply('You have to be an operator in this channel to use !config.');
			return;
		}
		
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}
		
		$parts = explode(' ', $this->data['text'], 4);
		if(sizeof($parts) < 2) {
			$this->printUsage();
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
					$this->printUsage();
					return;
				}
				$this->_setConfig($trigger, $parts[2], $parts[3]);
			break;
			default:
				$this->printUsage();
			break;
		}
	}
	
	private function _listConfig($trigger) {
		$Plugin = $this->getPluginByTrigger($trigger);
		if($Plugin === false) {
			$this->reply('This plugin doesn\'t exist.');
			return;
		}
		
		$config = $Plugin->getConfigList();
		list($crap, $plugin_name) = explode('_', get_class($Plugin), 2);
		
		if(empty($config)) {
			$this->reply('Plugin '.$plugin_name.' doesn\'t have any config variables');
			return;
		}
		
		
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
			
			$this->reply(sprintf($output,
				$name,
				(is_bool($def['value']) ? ($def['value'] ? 'true' : 'false') : $def['value']),
				$options,
				$def['description']
			));
		}
	}
	
	private function _setConfig($trigger, $name, $value) {
		$Plugin = $this->getPluginByTrigger($trigger);
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
	
}

?>
