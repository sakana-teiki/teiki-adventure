<?php
/*
  このファイルでは主に環境変数を設定します。
  general.phpに書くべきかどうかは、主に「公開するレンタルサーバーと開発環境で値が変わるかどうか？」を判断基準にするとよいと思います。
  こちらでは公開するレンタルサーバーと開発環境で値が変わりうると思われるものをまとめています。
*/

// 環境設定
// 開発環境か本番環境かを指定します。開発環境であれば'development'、本番環境であれば'production'を指定します。
$GAME_CONFIG['ENVIRONMENT'] = 'development'; // 開発環境ではこちらを有効化
//$GAME_CONFIG['ENVIRONMENT'] = 'production'; // 本番環境ではこちらを有効化

// URL設定
$GAME_CONFIG['ABSOLUTE_URI'] = 'http://localhost/teiki/'; // ゲームが公開されるURIをプロトコルから指定します。
$GAME_CONFIG['URI']          = '/teiki/';                 // ゲームが公開されるURIを指定します。例えば'http://example.com/ta/'で公開する場合は'/ta/'を、'http://example.com/'で公開する場合は'/'を指定します。

// MySQL設定
$GAME_CONFIG['MYSQL_HOST']     = 'localhost';       // 使用するMySQLのホストです。
$GAME_CONFIG['MYSQL_PORT']     = 3306;              // 使用するMySQLのポートです。MySQL/MariaDBは通常3306ポートを使用します。
$GAME_CONFIG['MYSQL_DBNAME']   = 'teiki_adventure'; // 使用するMySQLのDB名です。
$GAME_CONFIG['MYSQL_USERNAME'] = 'teiki';           // 使用するMySQLのユーザー名です。
$GAME_CONFIG['MYSQL_PASSWORD'] = 'dbpassword';      // 使用するMySQLのパスワードです。

// 初期化キー設定
$GAME_CONFIG['INITIALIZE_KEY'] = 'init'; // ゲームデータの初期化に使用するパスワードです。公開する環境では必ず複雑なパスワードに変更してください。

// セッション設定
$GAME_CONFIG['SESSION_NAME']     = 'teiki_session';                  // セッション名を指定します。 
$GAME_CONFIG['SESSION_LIFETIME'] = 60 * 60 * 24 * 14;                // セッション及びクッキーの有効時間を秒数で設定します。
$GAME_CONFIG['SESSION_PATH']     = GETENV('GAME_ROOT').'/sessions/'; // セッションの保存先を指定します。
?>