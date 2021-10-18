<?php
/*
  このファイルでは主に環境変数を設定します。
  general.phpに書くべきかどうかは、主に「公開するレンタルサーバーと開発環境で値が変わるかどうか？」を判断基準にするとよいと思います。
  こちらでは公開するレンタルサーバーと開発環境で値が変わりうると思われるものをまとめています。
*/

//URL設定
$GAME_CONFIG['URI'] = '/teiki/'; // ゲームが公開されるURIを指定します。例えば'http://example.com/ta/'で公開する場合は'/ta/'を、'http://example.com/'で公開する場合は'/'を指定します。

//MySQL設定
$GAME_CONFIG['MYSQL_HOST']     = 'localhost';       // 使用するMySQLのホストです。
$GAME_CONFIG['MYSQL_PORT']     = 3306;              // 使用するMySQLのポートです。MySQL/MariaDBは通常3306ポートを使用します。
$GAME_CONFIG['MYSQL_DBNAME']   = 'teiki_adventure'; // 使用するMySQLのDB名です。
$GAME_CONFIG['MYSQL_USERNAME'] = 'teiki';           // 使用するMySQLのユーザー名です。
$GAME_CONFIG['MYSQL_PASSWORD'] = 'dbpassword';      // 使用するMySQLのパスワードです。

?>