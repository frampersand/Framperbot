<?php
define('BOT_TOKEN', '/**** TELEGRAM BOT TOKEN ****/');
define('GENIUS_TOKEN', '/**** GENIUS API TOKEN ****/');
require_once("genius/autoload.php");
require_once("telegram.php");
require_once("lyricfetch.php");
$telegram = new Telegram(BOT_TOKEN);
$lyricBot = new LyricFetch();

$result = $telegram->getData();
$data = $lyricBot->processMessage($result, $telegram);

if($data["inline"]){
    array_pop($data);
    $responses = $lyricBot->getInlineResponse($data);
    $content = array("inline_query_id" => $result["inline_query"]["id"], "results" => json_encode($responses), "cache_time" => 60);
    $telegram->inlineQuery($content);
}else{
    array_pop($data);
    $response = $lyricBot->getResponse($data);
    $content = array('chat_id' => $result["message"]["chat"]["id"], 'text' => $response, "parse_mode" => 'html');	
	$telegram->sendMessage($content);
}

?>