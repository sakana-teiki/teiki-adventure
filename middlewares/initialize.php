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
  session_cache_limiter('private_no_expire'); // フォーム再送信の確認が出ないようにする
  session_name($GAME_CONFIG['SESSION_NAME']); // セッション名を指定 

  session_start();

  $GAME_LOGGEDIN = isset($_SESSION['ENo']); // ログインしているかどうか
  $GAME_LOGGEDIN_AS_ADMINISTRATOR = isset($_SESSION['administrator']) && $_SESSION['administrator']; // 管理者としてログインしているかどうか

  // メンテナンス状態及びキャラクターの削除状態の取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `maintenance`,
      IFNULL((SELECT `deleted` FROM `characters` WHERE `ENo` = :ENo), false) AS `deleted`
    FROM
      `game_status`;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);
  
  $result     = $statement->execute();
  $gameStatus = $statement->fetch();

  if (!$result || !$gameStatus) {
    // SQLの実行に失敗した場合

    // まだ初期化が行われていないだけかチェック
    // 全テーブルを取得
    $statement = $GAME_PDO->prepare("SHOW TABLES;");
    $result = $statement->execute();

    if (!$result) { // これにも失敗した場合は500(Internal Server Error)を返して処理を中断
      responseError(500);
    }

    $tables = $statement->fetchAll();

    if (!$tables) { // テーブルがないならまだ初期化が行われていないだけのため適切な値をセットして処理を続行
      $gameStatus = array(
        'maintenance' => false,
        'deleted'     => false
      );      
    } else {
      responseError(500); // ではない場合は不明なエラーのため500(Internal Server Error)を返して中断
    }
  }

  if ($gameStatus['deleted']) {
    header('Location:'.$GAME_CONFIG['URI'].'signout', true, 302); // キャラクターが削除されていた場合はログアウトページにリダイレクト
  }

  $GAME_MAINTENANCE = $gameStatus['maintenance'];
  
  if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR && $GAME_MAINTENANCE) {
    responseError(503); // 管理者以外ははメンテナンス中なら503(Service Unavailable)を表示して中断
  }

?>