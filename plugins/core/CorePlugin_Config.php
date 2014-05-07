<?php

class CorePlugin_Config extends Plugin {
	
	public $triggers = array('!config', '!conf');
	public $usage = '<trigger> [<name>] [<value>]';
	
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
		
		$parts = explode(' ', $this->data['text'], 3);
		$plugin = $parts[0];
		if(isset($parts[1])) $name  = $parts[1];
		if(isset($parts[2])) $value = $parts[2];
		
		$Plugin = $this->findPlugin($plugin);
		if($Plugin === false) {
			$this->reply('This plugin doesn\'t exist.');
			return;
		}
		
		if(isset($value)) {
			$this->_setConfig($Plugin, $name, $value);
		} elseif(isset($name)) {
			$this->_showConfig($Plugin, $name);
		} else {
			$this->_listConfig($Plugin);
		}
	}
	
	private function _listConfig($Plugin) {
		$config = $Plugin->getConfigList();
		list($crap, $plugin_name) = explode('_', get_class($Plugin), 2);
		
		if(empty($config)) {
			$this->reply('Plugin '.$plugin_name.' doesn\'t have any config variables');
			return;
		}
		
		
		$this->reply('Available config variables for plugin '.$plugin_name.':');
		
		foreach($config as $name => $def) {
			if(!isset($def['type'])) $def['type'] = 'string';
			
			$output = '%s: %s (%s) - %s';
			
			switch($def['type']) {
				case 'enum':
					$options = '';
					foreach($def['options'] as $option) {
						$options.= $option.', ';
					}
					$options = substr($options, 0, -2);
				break;
				case 'int':
					$options = 'any number';
				break;
				case 'range':
					$options = $def['min'].'-'.$def['max'];
				break;
				case 'string':
					$options = 'anything';
				break;
				case 'unsigned_int':
					$options = 'any natural number';
				break;
			}
			
			$this->reply(sprintf($output,
				$name,
				$def['value'],
				$options,
				$def['description']
			));
		}
	}
	
	private function _showConfig($Plugin, $name) {
		list($crap, $plugin_name) = explode('_', get_class($Plugin), 2);
		
		$value = $Plugin->getConfig($name);
		if($value === false) {
			$this->reply('There is no such config variable in plugin '.$plugin_name);
			return;
		}
		
		$this->reply(sprintf(
			"Config variable \x02%s\x02 of plugin \x02%s\x02 for %s is set to \x02%s\x02.",
				$name,
				$plugin_name,
				($this->Channel === false ? 'user '.$this->User->name : 'channel '.$this->Channel->name.' on '.$this->Server->id),
				$value
		));
	}
	
	private function _setConfig($Plugin, $name, $value) {
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
