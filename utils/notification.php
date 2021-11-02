<?php

// 主にDiscord通知に関する機能をまとめた関数群です。

/**
* Webhookを用いてDiscordに通知を送信します。
* 
* @param string $url 送信先のwebhookのURLです。
* @param string $message 送信する通知の内容です。
*/
function notifyDiscord($url, $message) {
  global $GAME_CONFIG;

  @file_get_contents($url, false, stream_context_create(array(
    'http' => array(
      'method' => 'POST',
      'header' => 'Content-Type: application/json',
      'content' => json_encode(array(
          'content' => $GAME_CONFIG['DISCORD_NOTIFICATION_PREFIX'].$message
        ))
    )
  )));
  
  return;
}

?>