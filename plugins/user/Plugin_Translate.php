<?php

class Plugin_Translate extends Plugin {
	
	protected $config = array(
		'language' => array(
			'type' => 'enum',
			'options' => array('de', 'en'),
			'default' => 'en',
			'description' => 'Determines to which language !translate should auto-translate'
		)
	);
	
	public $triggers = array('!translate', '!de-en', '!de-fr', '!de-it', '!de-nl', '!de-pl', '!de-sv', '!de-es', '!de-no', '!en-de',
						'!fr-de', '!it-de', '!nl-de', '!pl-de', '!sv-de', '!es-de', '!no-de', '!en-fr', '!en-it', '!en-nl',
						'!en-pl', '!en-sv', '!en-es', '!en-no', '!fr-en', '!it-en', '!nl-en', '!pl-en', '!sv-en', '!es-en',
						'!no-en');

	
	function isTriggered() {
		if(!isset($this->data['text'])) {
			$this->reply("Usage: ".$this->data['trigger']." <text>");
			return;
		}

		if ($this->data['trigger'] == '!translate') {
			$sl = '';
			$tl = $this->getConfig('language');
		} else {
			$trigger = substr($this->data['trigger'],1);
			$tmp = explode("-",$trigger);
			$sl = $tmp[0];
			$tl = $tmp[1];
		}

		$res = $this->googleTranslate($this->data['text'], $sl, $tl);

		if (empty($res['translation']))  {
			$this->reply("\x02Something weird occurred \x02");
			return;
		}

		$res['translation'] = preg_replace_callback(
			'/\\\u([0-9a-f]{4})/',
			create_function(
				'$m',
				'return chr(hexdec($m[1]));'
			),
			$res['translation']
		);

		$res['translation'] = $this->unhtmlentities($res['translation']);

		if (isset($res['detected_lang']))
			$this->reply("\x02Translation (autodetect: '".$res['detected_lang']."'): \x02".$res['translation']);
		else 
			$this->reply("\x02Translation: \x02".$res['translation']);
	}


	private function googleTranslate($text, $from='', $to = 'de') {
		$host = 'ajax.googleapis.com';
		$get  = '/ajax/services/language/translate?v=1.0&q='.rawurlencode($text).'&langpair='.rawurlencode($from.'|'.$to);
		
		$HTTP = new HTTP($host);
		$HTTP->set('useragent', 'Nimda'); // Google wants a User-Agent - else it will give an error about service abuse
		$html = $HTTP->GET($get);
		
		preg_match('/{"translatedText":"(.*?)"(,"detectedSourceLanguage":"([a-z-]+)")?}/i', $html, $matches);

		if (empty($matches)) 
			return false;
		if (isset($matches[3]))
			return array('translation' => $matches[1], 'detected_lang' => $matches[3]);
		
		return array('translation' => $matches[1]);
	}
	

	function unhtmlentities($string) {
		// replace numeric entities
		$string = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $string);
		$string = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $string);
		// replace literal entities
		$trans_tbl = get_html_translation_table(HTML_ENTITIES);
		$trans_tbl = array_flip($trans_tbl);
		return strtr($string, $trans_tbl);
	}


	
}

?>
