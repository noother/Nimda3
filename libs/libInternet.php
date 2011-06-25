<?php

class libInternet {
	
	static function googleResults($string) {
		$host   = "www.google.com";
		$lang	= "de";
		$get    = "/search?q=".urlencode($string)."&hl=".$lang."&safe=off";
		$result = libHTTP::GET($host,$get);
		
		$result = implode($result['content'],"\n");
		
		preg_match('#Ungef.hr (.*?) Ergebnisse#',$result,$arr);
		
		if(empty($arr)) return 0;
	return str_replace('.','',$arr[1]);
	}
	
	static function googleTranslate($text, $from='', $to = 'de') {
		$host = 'ajax.googleapis.com';
		$get  = '/ajax/services/language/translate?v=1.0&q='.rawurlencode($text).'&langpair='.rawurlencode($from.'|'.$to);
		
		$result = libHTTP::GET($host, $get);
		preg_match('/{"translatedText":"(.*?)"}/i', $result['content'][0], $matches);

		if (empty($matches)) return false;
	return $matches[1];
	}
	
	static function youtubeID($string) {
		if(preg_match('#^http://(www\.)?youtube\.com/.*?(\?|&)v=([a-zA-Z0-9_-]{11}?)($|&)#',$string,$arr)) {
			return $arr[3];
		} else {
			return false;
		}
	}
	
	static function getYoutubeData($youtube_id) {
		if(empty($youtube_id)) return false;
		$res = libHTTP::GET('gdata.youtube.com','/feeds/api/videos/'.$youtube_id);
		if($res['raw'] == 'Invalid id') return false;
		
		$xml = simplexml_load_string($res['raw']);
		
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
	
}

?>
