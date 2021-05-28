<?php

class CorePlugin_Help extends Plugin {
	
	public $triggers = array('!help');
	
	public $hideFromHelp = true;
	public $helpText = 'I am not able to help you.';
	
	private $Target;
	
	function isTriggered() {
		if(isset($this->data['text'])) {
			$this->printHelpText($this->data['text']);
		} else {
			$last_triggered = $this->User->getVar('help_last_triggered', 0);
			
			if($this->Bot->time >= $last_triggered + 60) {
				$this->printHelp();
				$this->User->saveVar('help_last_triggered', $this->Bot->time);
			} else {
				$this->reply('You can only show !help once a minute.');
			}
		}
	}
	
	function printHelp() {
		$categories = array();
		foreach($this->Bot->plugins as $Plugin) {
			if($Plugin->hideFromHelp) continue;
			
			if($Plugin->helpTriggers !== false) $triggers = $Plugin->helpTriggers;
			elseif(!empty($Plugin->triggers)) $triggers = $Plugin->triggers;
			else continue;
			
			foreach($triggers as &$trigger) {
				if($trigger[0] == '!') $trigger = substr($trigger, 1);
			}
			
			if(!isset($categories[$Plugin->helpCategory])) $categories[$Plugin->helpCategory] = array();
			$categories[$Plugin->helpCategory] = array_merge($categories[$Plugin->helpCategory], $triggers);
		}
		
		$this->User->privmsg('Available commands (Type a \'!\' in front of each command)');
		
		foreach($categories as $category => $commands) {
			$this->User->privmsg($text = "\x02".$category."\x02: ".implode(', ', $commands));
		}
		
		$this->User->privmsg('To get more information about a command, type '.$this->data['trigger'].' <command>.');
		$this->User->privmsg('Nimda is open source. You can find its source code at https://github.com/noother/Nimda3. If you want Nimda in your own channel, either run your own copy, or ask noother to let Nimda join your server/channel. He\'s happy if his copy runs on as many servers/channels possible :)');
	}
	
	function printHelpText($trigger) {
		$Plugin = $this->findPlugin($trigger);
		if(!$Plugin) {
			$this->reply('This command doesn\'t exist.');
		} else {
			$Plugin->data['trigger'] = $trigger;
			$usage = $Plugin->getUsage();
			
			$text = '';
			if($usage !== false) {
				$text.= $usage." \x02=>\x02 ";
			}
			$text.= $Plugin->getHelpText();
			
			if($Plugin->enabledByDefault === false) {
				$text.= " | This plugin is disabled by default. See \x02!config\x02";
			}
			
			if(sizeof($Plugin->getConfigList()) > 1) {
				$text.= " | This Plugin has \x02!config\x02 variables available.";
			}
			
			$this->reply($text);
		}
	}
	
}

?>
