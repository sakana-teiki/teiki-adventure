<?php
/*
  設定値を読み込んだり、DBに接続したり、セッションを開始したりする処理です。
  このミドルウェアは必ず読み込むようにしてください。
*/

  // 設定値の読み込み
  require_once GETENV('GAME_ROOT').'/configs/environment.php';
  require_once GETENV('GAME_ROOT').'/configs/general.php';

  // 全体で利用可能な関数の読み込み
  require_once GETENV('GAME_ROOT').'/utils/general.php';

  // タイムゾーンの指定
  date_default_timezone_set($GAME_CONFIG['DEFAULT_TIMEZONE']);

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
  //session_cache_limiter('private_no_expire'); // フォーム再送信の確認が出ないようにする（指定しているとログイン状態を切り替えたときにキャッシュが残って影響あり？要検証）
  session_name($GAME_CONFIG['SESSION_NAME']); // セッション名を指定 

  session_start();

  $GAME_LOGGEDIN = isset($_SESSION['ENo']); // ログインしているかどうか
  $GAME_LOGGEDIN_AS_ADMINISTRATOR = isset($_SESSION['administrator']) && $_SESSION['administrator']; // 管理者としてログインしているかどうか

  // メンテナンス状態の取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `maintenance`
    FROM
      `game_status`;
  ");
  
  $result     = $statement->execute();
  $gameStatus = $statement->fetch();

  if (!$result || !$gameStatus) {
    $GAME_MAINTENANCE = false;
  } else {
    $GAME_MAINTENANCE = $gameStatus['maintenance'];
  }

  // メンテナンス中なら503を表示して中断
  if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR && $GAME_MAINTENANCE) {
    responseError(503);
  }

?>