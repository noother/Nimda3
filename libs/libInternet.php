<?php

require_once('libs/libHTTP.php');


class libInternet {
	
	static function googleResults($string) {
		$html = libHTTP::GET('http://www.google.com/search?q='.urlencode($string).'&hl=en&safe=off');
		if(preg_match('#<div>Results .+? of (?:about )?<b>([\d,]+?)</b>#', $html, $arr)) {
			return str_replace(',', '', $arr[1]);
		} else {
			return 0;
		}
	}
	
	static function googleTranslate($text, $from='', $to = 'de') {
		$host = 'ajax.googleapis.com';
		$get  = '/ajax/services/language/translate?v=1.0&q='.rawurlencode($text).'&langpair='.rawurlencode($from.'|'.$to);
		
		$raw = libHTTP::GET('http://'.$host.$get);
		preg_match('/{"translatedText":"(.*?)"}/i', $raw, $matches);

		if (empty($matches)) return false;
	return $matches[1];
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
	
}

?>
