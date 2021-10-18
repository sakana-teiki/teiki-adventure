<?php
/*
  設定値を読み込んだり、DBに接続したり、セッションを開始したりする処理です。
  このミドルウェアは必ず読み込むようにしてください。
*/

  // 設定値の読み込み
  require_once GETENV('GAME_ROOT').'/configs/environment.php';
  require_once GETENV('GAME_ROOT').'/configs/general.php';

  // DBに接続
  try {
    $GAME_PDO = new PDO('mysql:dbname='.$GAME_CONFIG['MYSQL_DBNAME'].';host='.$GAME_CONFIG['MYSQL_HOST'].':'.$GAME_CONFIG['MYSQL_PORT'], $GAME_CONFIG['MYSQL_USERNAME'], $GAME_CONFIG['MYSQL_PASSWORD']);
  } catch (PDOException $e) {
    print('Error:'.$e->getMessage());
    exit;
  }

  // セッションの開始
  ini_set('session.gc_maxlifetime' , $GAME_CONFIG['SESSION_LIFETIME']); // セッションの有効時間を指定
  ini_set('session.cookie_lifetime', $GAME_CONFIG['SESSION_LIFETIME']); // クッキーの有効時間を指定
  session_name($GAME_CONFIG['SESSION_NAME']);  // セッション名を指定
  
  session_start();

  $GAME_LOGGEDIN = isset($_SESSION['ENo']); // ログインしているかどうか
  
?>