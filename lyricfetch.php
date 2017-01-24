<?php
class LyricFetch {
	
	public function processMessage($msg, $telegram) {
		if (isset($msg["inline_query"])) {
			$id = $msg["inline_query"]["id"];
			$query = $msg["inline_query"]["query"];
			$query = str_replace("'", "", $query);
			$user = $msg["inline_query"]["from"]["id"];
			
			if(preg_match("/^.*? - .*?$/i", $query)){
				$match = $this->get_genius($query);
				if ($match == NULL || empty($match)){
					$match = $this->songlyrics($query);
				}
				$match["inline"] = true;
				return $match;
			}
			else{
				$match = $this->get_genius($query, true);
				if ($match == NULL || empty($match)){
					$match = $this->songlyrics($query, true);
				}
				$match["inline"] = true;
				return $match;
			}
		}else{
			$text = $msg['message']['text'];
			$user = $msg['message']['chat']['id'];
			if($text{0} == '/') {
				$c = explode(' ', $text);
				switch(strtolower(trim($c[0]))) {
					case '/start':
						$response = "Welcome, I'm a test bot. And as such, my functions change from time to time depending on my maker's project at the time.\nWrite /help to know my current function.";
						break;
					case '/help':
						$response = "In this moment, my function is to bring you a song lyrics given the artist and the song title in the format 'Artist - Song' (eg. The White Stripes - Seven Nation Army). I'm still in development to have a bigger song database.\n\nUpdate: I now have inline query capabilities, just type my name followed by the name of your song in the format of 'Artist - Song', or just the name of the song, and share them with your friends\n\nEg. @FrampersandBot Queen - Bohemian Rhapsody, or @FrampersandBot Bohemian Rhapsody";
						break;
					default:
						$response = "The command you entered is invalid";
						break;
				}
				$content = array('chat_id' => $msg["message"]["chat"]["id"], 'text' => $response, "parse_mode" => 'html');	
				$telegram->sendMessage($content);
			}
			else{
				if(!preg_match("/.*? - .*?/i", $text)){
					$text = "Please, write the artist and song title in the format of 'Artist - Song'\ne.g. Queen - Bohemian Rhapsody.";
					$content = array('chat_id' => $msg["message"]["chat"]["id"], 'text' => $response, "parse_mode" => 'html');	
					$telegram->sendMessage($content);
				}
				else{
					$match = $this->get_genius($text);
					if ($match == NULL || empty($match)){
						$match = $this->songlyrics($text);
					}
					$match["inline"] = false;
					return $match;
				}
			}		
		}
	}
	
	public function getInlineResponse($array){
		$response = array();
		$id = 0;
		foreach($array as $item){
			$header = "*{$item["song"]}*\n{$item["artist"]}\n";
			$description = "{$item["artist"]}";
			if($item["album"] != "Miscellaneous" && $item["album"] != ""){
				$header .= "_{$item["album"]}_\n";
				$description .= "\n{$item["album"]}";
			}
			$item["lyric"] = str_replace(array("[", "]", "\\"), array('_', '_', ''), $item["lyric"]);
			$item["lyric"] = str_replace(array("<b>","</b>", "<i>","</i>"), "", $item["lyric"]);
			$item["lyric"] = preg_replace("/_{2,}/i", "", $item["lyric"]);
			$item["lyric"] = preg_replace("/\*{2,}/i", "", $item["lyric"]);
			$header .= "\n";
			$responseText = $header.$item["lyric"];
			$response[] = [
					"type" => "article",
					"id" => ''.$id.'',
					"title" => $item["song"],
					"description" => $description,
					"thumb_url" => ''.$item["img"].'',
					"parse_mode" => "markdown",
					"message_text" => ''.$responseText.'',
				  ];
			$id++;
		}
		return $response;
	}
	
	public function getResponse($array){
		$header = "<b>{$array["song"]}</b>\n{$array["artist"]}\n";
		if($array["album"] != "Miscellaneous" && $array["album"] != ""){
			$header .= "<i>{$array["album"]}</i>\n";
		}
		$header .= "\n";
		$response = $header.$array["lyric"];
		return $response;
	}
	
/************* FUNCTIONS TO FETCH DATA FROM GENIUS **********************/
/***************************** START ************************************/
	
	private function get_genius($query, $multi = false){
		echo "Searching on Genius: {$query} <br>";
		$geniusphp = new \Genius\Genius(GENIUS_TOKEN);
		$songdata = array();
		if($multi){
			$search = $geniusphp->search->get($query);
			foreach($search->response->hits as $item){
				$song = $geniusphp->songs->get($item->result->id)->response->song;
				preg_match_all("/<script crossorigin src='(.*?)'><\/script>/i", $song->embed_content, $geniusjs);
				if(!empty($song->album->cover_art_url)){
					$songdata[] = [
						"song" => $song->title,
						"artist" => $song->primary_artist->name,
						"album" => $song->album->name,
						"img" => $song->album->cover_art_url,
						"lyric" => $this->get_genius_lyrics_by_js("https:".$geniusjs[1][0]),
					];
				}
			}
		}else{
			$string = explode(" - ", $query);
			$artist = $string[0];
			$title = $string[1];
			$search = $geniusphp->search->get($title." ".$artist);
			foreach($search->response->hits as $item){
				if(strpos(strtolower($item->result->primary_artist->name), strtolower($artist)) !== false || strtolower($item->result->primary_artist->name) == strtolower($artist)){
					$song = $geniusphp->songs->get($item->result->id)->response->song;
					preg_match_all("/<script crossorigin src='(.*?)'><\/script>/i", $song->embed_content, $geniusjs);
					$songdata = [
						"song" => $song->title,
						"artist" => $song->primary_artist->name,
						"album" => $song->album->name,
						"img" => $song->album->cover_art_url,
						"lyric" => $this->get_genius_lyrics_by_js("https:".$geniusjs[1][0]),
					];
					break;
				}
			}
		}
		return $songdata;
	}
  
	private function get_genius_lyrics($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, trim($url));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36');
		$html = curl_exec($ch);
		curl_close($ch);
		$html = str_replace(array("\t","\r","\n"), "", $html);
		preg_match_all("/<lyrics class=\"lyrics\".*?>(.*?)<\/lyrics>/i", $html, $block);
		array_shift($block);
		$lyrics = str_replace("<br>", "\n", $block[0]);
		$lyrics = preg_replace("/(<.*?>)/i", "", $lyrics);
		$lyrics = preg_replace("/(googletag.*?; }\);)/i", "", $lyrics);
		$lyrics = ltrim($lyrics[0]);
		return $lyrics;
	}

	private function get_genius_lyrics_by_js($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, trim($url));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36');
		$html = curl_exec($ch);
		curl_close($ch);
		$html = str_replace(array('\\\\\\','\\\n'), "", $html);
		$html = str_replace("<\\/div>", "</div>", $html);
		$html = str_replace("<\\/p>", "</p>", $html);
		$html = str_replace(array("\t","\r","\n"), "", $html);
		preg_match_all("/<div class=\"rg_embed_body\">.*?<p>(.*?)<\/p>.*?<\/div>/i", $html, $block);
		array_shift($block);
		$lyrics = preg_replace("/(<a .*?>)/i", "", $block[0][0]);
		$lyrics = str_replace("<\\/a>", "", $lyrics);
		$lyrics = str_replace("<br>", "\n", $lyrics);
		$lyrics = str_replace("\\'", "'", $lyrics);
		$lyrics = str_replace(array("<strike>", "<\\/strike>"), "", $lyrics);
		return $lyrics;
	}
	
/************* FUNCTIONS TO FETCH DATA FROM GENIUS **********************/
/****************************** END *************************************/	
	

/************* FUNCTIONS TO FETCH DATA FROM SONGLYRICS ******************/
/***************************** START ************************************/
	
	private function songlyrics($query, $multi = false){
		echo "Searching on Songlyrics: {$query} <br>";
		$data = explode(" - ", $query);
		$artist = $data[0];
		$query = $data[1];
		$query = urlencode($query);
		$url = "http://www.songlyrics.com/index.php?section=search&searchIn3=song&searchW={$query}";
		$contents = $this->get_contents_clean($url);
		$results = $this->regex("<!-- end topnav -->(.*?)<!--end coltwo-center-->", $contents);
		if($multi){
			$res = $this->songlyrics_full_crawler($results);
			if(!is_null($res)){
				$rt = array();
				foreach($res as $item){
					$header = "<b>{$item["song"]}</b>\n{$item["artist"]}";
					if($item["album"] != "Miscellaneous")
						$header .= "\n<i>{$item["album"]}</i>";
					$header .= "\n\n";
					$rt[] = [
							"artist" => $item["artist"],
							"song" => $item["song"],
							"album" => $item["album"],
							"img" => $item["img"],
							"lyric" => $this->getlyric($item["link"])	
						];
				}
				return $rt;
			}
			return null;
		}
		$res = $this->songlyrics_crawler($results, $artist);
		if(!is_null($res)){
			return $res;
		}
		$ul = $this->regex("<ul class=\"pagination\">(.*?)<\/ul>", $contents);
		$pags = $this->regex("<li.*?><a href.*?>(.*?)<\/a><\/li>", $ul[0]);
		array_pop($pags);
		$pags = array_reverse($pags);
		$count = $pags[0];
		for($i=2; $i<=$count; $i++){
			$url = "http://www.songlyrics.com/index.php?section=search&searchIn3=song&searchW={$query}&pageNo={$i}";
			$contents = $this->get_contents_clean($url);
			$results = $this->regex("<!-- end topnav -->(.*?)<!--end coltwo-center-->", $contents);
			$res = $this->songlyrics_crawler($results, $artist);
			if(!is_null($res)){
				return $res;
			}
		}
		return null;
	}	

	private function songlyrics_crawler($contents, $artist){
		preg_match_all('/<div class=\"serpresult\">.*?<a href="(.*?)" title="(.*?)"><img src="(.*?)".*?<div class="serpdesc-2"><p>by <a.*?>(.*?)<\/a> on album <a.*?>(.*?)<\/a>.*?/i', $contents[0], $res);
		array_shift($res);
		$matchto = strtolower($artist);
		$count = count($res[0]);
		for($i=0;$i<$count;$i++){
			$current = strtolower($res[3][$i]);
			if(strpos($current, $matchto) !== false || $current == $matchto){
		$lyric = $this->getlyric($res[0][$i]);
				$match = [
					"artist" => $res[3][$i],
					"song" => $res[1][$i],
					"album" => $res[4][$i],
					"img" => $res[2][$i],
					"lyric" => $lyric
				];
				return $match;
			}
		}
		return null;
	}

	private function songlyrics_full_crawler($contents){
		preg_match_all('/<div class=\"serpresult\">.*?<a href="(.*?)" title="(.*?)"><img src="(.*?)".*?<div class="serpdesc-2"><p>by <a.*?>(.*?)<\/a> on album <a.*?>(.*?)<\/a>.*?/i', $contents[0], $res);
		array_shift($res);
		$matchto = strtolower($artist);
		$count = count($res[0]);
		$list = array();
		if($count > 10)
			$count = 10;
		for($i=0;$i<$count;$i++){
			$list[] = [
				"artist" => $res[3][$i],
				"song" => $res[1][$i],
				"album" => $res[4][$i],
				"lyric" => $this->getlyric($res[0][$i]),
				"img" => $res[2][$i]
			];
		}
		return $list;
	}

	private function getlyric($url){
		$content = $this->get_contents_clean($url);
		$lyrc = $this->regex("<p id=\"songLyricsDiv\".*?>(.*?)<\/p>", $content);
		$reply = preg_replace("/(<!.*?<.*?>)/i", "", $lyrc[0]);
		$reply = str_replace("<br />", "\n", $reply);
		$reply = preg_replace("/(<.*?>)/i", "", $reply);
		echo $reply;
		return $reply;
	}

/************* FUNCTIONS TO FETCH DATA FROM SONGLYRICS ******************/
/***************************** END ************************************/
	
/*************************************************************/	
	
	public function sp_char($string) {
	$string = preg_replace_callback('/&#(\d+);/', function($char) {
		return chr(intval($char[1]));
	}, $string);
	return html_entity_decode($string);
	}
	
	public function replace_sp_chars($string){
	$string = trim($string);
	$string = str_replace(array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'), array('a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'), $string);
	$string = str_replace(array('é', 'è', 'ë', 'ê', 'ē', 'É', 'È', 'Ê', 'Ë'), array('e', 'e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'), $string);
	$string = str_replace(array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'), array('i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'),	$string);
	$string = str_replace(array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'), array('o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'), $string);
	$string = str_replace(array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'), array('u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'), $string);
	$string = str_replace(array('ñ', 'Ñ', 'ç', 'Ç'), array('n', 'N', 'c', 'C',), $string);

	//Esta parte se encarga de eliminar cualquier caracter extraño
	$string = str_replace(
	array("º", "-", "#", "|", "!", '"', "·", "$", "%", "&", "/", "(", ")", "?", "'", "[", "^", "<code>", "]", "+", "}", "{", "¨", "´", ">", "< ", ";", ",", ":", "."), '', $string);
	return $string;
	}
	
	private function get_contents_clean($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, trim($url));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.106 Safari/537.36');
		$html = curl_exec($ch);
		curl_close($ch);
		$html = str_replace(array("\t","\r","\n"), "", $html);
		return $html;
	}

	private function regex($p, $s) {
		preg_match_all('/'.$p.'/i', $s, $r);
		return $r[1];
	}
}
?>