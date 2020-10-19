<?php

/**
 * Telegram bot for receiving notifications on GitHub commits
 * Author: Jacopo Jannone (https://www.jacopojannone.com)
 * 
 * HMAC and headers verification code, including the `array_matches`
 * functions, were taken from the following Gist and not written by me:
 * https://gist.github.com/jplitza/88d64ce351d38c2f4198
 */

define('BOT_TOKEN', ''); // Telegram bot token goes here
define('CHAT_ID', ''); // Telegram chat_id goes here
define('REPOSITORY_NAME', ''); // Full repository name (e.g. 'johndoe/myrepo')
define('HMAC_SECRET', ''); // HMAC secret goes here

function array_matches($have, $should, $name = 'array') {
	$ret = true;
	if(is_array($have)) {
		foreach($should as $key => $value) {
			if(!array_key_exists($key, $have)) {
				$ret = false;
			}
			else if(is_array($value) && is_array($have[$key])) {
				$ret &= array_matches($have[$key], $value);
			}
			else if(is_array($value) || is_array($have[$key])) {
				$ret = false;
			}
			else if(!fnmatch($value, $have[$key])) {
				$ret = false;
			}
		}
	}
	else {
		$ret = false;
	}
	return $ret;
}

function send_message($text, $keyboard) {
	$sendto = 'https://api.telegram.org/bot'
			  . BOT_TOKEN
			  . '/sendmessage?chat_id='
			  . CHAT_ID
			  . '&text='
			  . urlencode($text)
			  . "&parse_mode=html&disable_web_page_preview=true&reply_markup="
			  . urlencode($keyboard);
    file_get_contents($sendto);
}


$post_data = file_get_contents('php://input');

if (! $post_data) {
    die();
}

$signature = hash_hmac("sha1", $post_data, HMAC_SECRET);

$required_data = array(
	'ref' => 'refs/heads/main',
	'repository' => array(
		'full_name' => REPOSITORY_NAME,
	),
);

$required_headers = array(
	'REQUEST_METHOD' => 'POST',
	'HTTP_X_GITHUB_EVENT' => 'push',
	'HTTP_USER_AGENT' => 'GitHub-Hookshot/*',
	'HTTP_X_HUB_SIGNATURE' => 'sha1=' . $signature,
);

$data = json_decode($post_data, true);

$headers_ok = array_matches($_SERVER, $required_headers, '$_SERVER');
$data_ok = array_matches($data, $required_data, '$data');

if ((! $headers_ok) || (! $data_ok)) {
    die();
}

foreach($data["commits"] as $commit) {
	$keyboard = json_encode(array(
		"inline_keyboard" => array(array(
			array("text" => "View on GitHub", "url" => $commit["url"])
		))
	), true);
	$text = "<b>New commit by "
			. $commit["author"]["username"]
			. "!</b>\n\n"
			. $commit["message"];
    send_message($text, $keyboard);
}

?>
