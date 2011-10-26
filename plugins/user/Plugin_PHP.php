<?php

class Plugin_PHP extends Plugin {
	
	protected $config = array(
		'language' => array(
			'type' => 'enum',
			'options' => array('de', 'en'),
			'default' => 'en',
			'description' => 'Function description will get displayed in this language'
		)
	);
	
	public $triggers = array('!php', '!phpmanual');
	private $redirects;
	private $notfoundtext = 'Nothing matches your query, try search:';

	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply('Usage: !php <function>');
			return;
		}

		
		$this->redirects = 0;
		$this->fetchFunctionDescription($this->data['text']);  
	}
	
	private function fetchFunctionDescription($func) {
		if($this->getConfig('language') == 'de') $host = 'de.php.net';
		else $host = 'php.net';
		
		$HTTP = new HTTP($host);
		$HTTP->setCookie('LAST_LANG', $this->getConfig('language'));
		$html = $HTTP->GET('/'.$func);
		
		if($html === false) {
			$this->reply('Timeout on contacting '.$host);
			return;
		}
	
		if (preg_match('/<span class=\"refname\">(.*?)<\/span> &mdash; <span class=\"dc\-title\">(.*?)<\/span>/si', $html, $match)) {
			$match[2] = str_replace(array("\n", "\r"), ' ', strip_tags($match[2]));

			preg_match('/<div class=\"methodsynopsis dc\-description\">(.*?)<\/div>/si', $html, $descmatch);

			$decl = isset($descmatch[1])?strip_tags($descmatch[1]):$match[1];
			$decl = html_entity_decode(str_replace(array("\n", "\r"), ' ', $decl));
			while(strstr($decl, '  ')) $decl = str_replace('  ', ' ', $decl);
			$decl = str_replace($func, "\x02".$func."\x02", $decl);
			$output =  $decl.' - '.html_entity_decode($match[2]).' ( http://'.$host.'/'.$func.' )';
			
			$this->reply(libString::isUTF8($output)?$output:utf8_encode($output));
		} else {    // if several possibilities
			$output = '';

			if (preg_match_all('/<a href=\"\/manual\/[a-z]+\/(?:.*?)\.php\">(?:<b>)?(.*?)(?:<\/b>)?<\/a><br/i', $html, $matches, PREG_SET_ORDER)) {
				if ($this->redirects++ < 2)
					$this->fetchFunctionDescription($matches[0][1]);
				else 
					$this->reply($this->notfoundtext.' http://'.$host.'/search.php?show=wholesite&pattern='.$this->data['text']);
				return;
			} else
				$output = $this->notfoundtext.' http://'.$host.'/search.php?show=wholesite&pattern='.$func;

			$this->reply(libString::isUTF8($output)?$output:utf8_encode($output));
		}
	}
	
}

?>
