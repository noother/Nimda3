<?php

class Plugin_Google extends Plugin {
	
	public $triggers = array('!google');
	
	public $usage = '<search_term>';
	public $helpText = 'Gives back a link to Google with your <search_term> and the approximate results.';
	public $helpCategory = 'Internet';
	
	protected $config = array(
		'language' => array(
			'type' => 'enum',
			'options' => array('de', 'en'),
			'default' => 'en',
			'description' => 'The google language to use'
		)
	);
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->printUsage();
			return;
		}
		
		$this->reply(sprintf('%s (Results: %s)',
			"http://www.google.com/search?q=".urlencode($this->data['text'])."&hl=".$this->getConfig('language')."&safe=off",
			number_format(libInternet::googleResults($this->data['text']), 0, ',', '.')
		));
	}
	
}

?>
