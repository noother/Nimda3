<?php

class Plugin_Google extends Plugin {
	
	public $triggers = array('!google');
    private $lang = 'de';
	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply('Usage: !google <search_term>');
			return;
		}

        $link = "http://www.google.com/search?q=".urlencode($this->data['text'])."&hl=".$this->lang."&safe=off";

        $results = number_format(libInternet::googleResults($this->data['text']),0,',','.');

        $output = $link." (Results: ";
        if(!$results) $output.= "0";
        else $output.= "~ ";
        $output.= $results.")";

        $this->reply($output);
	}
	
}

?>
