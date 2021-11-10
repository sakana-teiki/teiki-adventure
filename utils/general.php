<?php

// 全体で使用可能な関数群を指定します。
// このファイルに指定した関数は全てのページ（正確にはmiddlewares/initialize.phpを読み込んでいるページ）で利用可能です。

/**
* エラーページを表示して処理を終了します。
* 
* @param string $statusCode 送信するステータスコードです。
*/
function responseError($statusCode) {
  http_response_code($statusCode);
  include GETENV('GAME_ROOT').'/error-pages/'.$statusCode.'.php';
  exit;
}

?>