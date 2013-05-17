<?php

require_once('libs/libHTTP.php');


class libInternet {
	
	static function googleResults($string) {
		$html = libHTTP::GET('http://www.google.com/search?q='.urlencode($string).'&hl=en&safe=off');
		
		if(preg_match('#<div.+?>(?:About )?([\d,]+) results</div>#', $html, $arr)) {
			return (int)str_replace(',', '', $arr[1]);
		} else {
			return 0;
		}
	}
	
	static function googleTranslate($text, $from='auto', $to='de', $return_source_lang=false) {
		$HTTP = new HTTP('translate.google.com');
		$HTTP->set('useragent', 'Mozilla/5.0 (X11; Linux x86_64; rv:14.0) Gecko/20100101 Firefox/14.0.1');
		$html = $HTTP->GET('/?sl='.$from.'&tl='.$to.'&q='.urlencode($text));
		
		if(!preg_match('#<span id=result_box .+?>(.+?)</div>#', $html, $arr)) return false;
		
		$translation = html_entity_decode(strip_tags($arr[1]));
		$translation = preg_replace_callback('/&#([a-z0-9]+);/i', create_function('$a', 'return chr($a[1]);'), $translation);
		
		if($return_source_lang) {
			if(!preg_match('#<a id=gt-otf-switch href=.+?&sl=(.+?)&#', $html, $arr)) return false;
			if(!preg_match('#<option value='.preg_quote($arr[1]).'>(.+?)</option>#', $html, $arr)) return false;
			return array('translation' => $translation, 'source_lang' => $arr[1]);
		} else {
			return $translation;
		}
	}
	
	static function youtubeID($string) {
		if(
			preg_match('#youtube\.com/.*?(?:(?:(?:\?|&)v=)|(?:\#(?:./){4}))([a-zA-Z0-9_-]{11})#', $string, $arr) ||
			preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#', $string, $arr)
		) {
			return $arr[1];
		} else {
			return false;
		}
	}
	
	static function getYoutubeData($youtube_id) {
		if(empty($youtube_id)) return false;
		
		$html = libHTTP::GET('http://gdata.youtube.com/feeds/api/videos/'.$youtube_id);
		$xml = simplexml_load_string($html);
		if($xml === false) return false;
		
		$data = array();
		
		$media = $xml->children('http://search.yahoo.com/mrss/');
		$data['title'] = (string)$media->group->title;
		$data['description'] = (string)$media->group->description;
		$data['category'] = (string)$media->group->category;
		$data['keywords'] = explode(', ',$media->group->keywords);
		$data['link'] = (string)$media->group->player->attributes()->url;
		$data['duration'] = (int)$media->children('http://gdata.youtube.com/schemas/2007')->duration->attributes()->seconds;
		$data['thumbnails'] = array();
		foreach($media->group->thumbnail as $thumbnail) {
			array_push (
				$data['thumbnails'], array (
					'url' => (string)$thumbnail->attributes()->url,
					'width' => (int)$thumbnail->attributes()->width,
					'height' => (int)$thumbnail->attributes()->height
				)
			);
		}
		
		$data['published'] = strtotime($xml->published);
		$data['author'] = (string)$xml->author->name;
		
		$gd = $xml->children('http://schemas.google.com/g/2005')->rating->attributes();
		$data['rating'] = (float)$gd->average;
		$data['num_raters'] = (int)$gd->numRaters;
		
		$data['views'] = (int)$xml->children('http://gdata.youtube.com/schemas/2007')->statistics->attributes()->viewCount;
		
	return $data;
	}
	
	static function tinyURL($longurl) {
		return libHTTP::GET('http://tinyurl.com/api-create.php?url='.urlencode($longurl));
	}
	
	static function tinyURLDecode($tinyurl) {
		$parts = parse_url($tinyurl);
		$HTTP = new HTTP('tinyurl.com');
		$HTTP->set('auto-follow', false);
		$HTTP->GET($parts['path']);
		$header = $HTTP->getHeader();
		
		if(!isset($header['Location'])) return false;
		
	return $header['Location'];
	}
	
}

?>
