<?php

class Plugin_Google extends Plugin {
	
	public $triggers = array('!google');
	
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
			$this->reply('Usage: !google <search_term>');
			return;
		}

        $link = "http://www.google.com/search?q=".urlencode($this->data['text'])."&hl=".$this->getConfig('language')."&safe=off";

        $results = number_format(libInternet::googleResults($this->data['text']),0,',','.');

        $output = $link." (Results: ";
        if(!$results) $output.= "0";
        else $output.= "~ ";
        $output.= $results.")";

        $this->reply($output);
	}
	
}

?>
