<?php

class Plugin_PHP extends Plugin {
	
	protected $triggers = array('!php', '!phpmanual');
	private $redirects;
	private $language = 'de';
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
		$res = libHTTP::GET($this->language.'.php.net', '/'.$func, 'LAST_LANG='.$this->language);
	
		if (preg_match('/<span class=\"refname\">(.*?)<\/span> &mdash; <span class=\"dc\-title\">(.*?)<\/span>/si', $res['raw'], $match)) {
			$match[2] = str_replace(array("\n", "\r"), ' ', strip_tags($match[2]));

			preg_match('/<div class=\"methodsynopsis dc\-description\">(.*?)<\/div>/si', $res['raw'], $descmatch);

			$decl = isset($descmatch[1])?strip_tags($descmatch[1]):$match[1];
			$decl = html_entity_decode(str_replace(array("\n", "\r"), ' ', $decl));
			$decl = str_replace($func, "\x02".$func."\x02", $decl);
			$output =  $decl.' - '.html_entity_decode($match[2]).' ( http://'.$this->language.'.php.net/'.$func.' )';
			
			$this->reply(libString::isUTF8($output)?$output:utf8_encode($output));
		} else {    // if several possibilities
			$output = '';

			if (preg_match_all('/<a href=\"\/manual\/[a-z]+\/(?:.*?)\.php\">(?:<b>)?(.*?)(?:<\/b>)?<\/a><br/i', $res['raw'], $matches, PREG_SET_ORDER)) {
				if ($this->redirects++ < 2)
					$this->fetchFunctionDescription($matches[0][1]);
				else 
					$this->reply($this->notfoundtext.' http://'.$this->language.'.php.net/search.php?show=wholesite&pattern='.$this->data['text']);
				return;
			} else
				$output = $this->notfoundtext.' http://'.$this->language.'.php.net/search.php?show=wholesite&pattern='.$func;

			$this->reply(libString::isUTF8($output)?$output:utf8_encode($output));
		}
	}
	
}

?>
