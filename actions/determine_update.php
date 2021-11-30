<?php
  putenv('GAME_ROOT='.dirname(__DIR__));

  // .htaccessでSetEnvが使えず$_SERVER['DOCUMENT_ROOT']を使用するよう変更した場合用に、コマンドライン等からDOCUMENT_ROOTを設定できるように
  // GAME_ROOTをDOCUMENT_ROOTを使用する形に置換していないのであれば特に気にする必要はありません
  // 書式：php filename.php --document-root="path"
  foreach ($argv as $arg) {
    if (strpos($arg, '--document-root=') === 0) {
      $_SERVER['DOCUMENT_ROOT'] = substr($arg, strlen('--document-root='));
    }
  }

  require_once GETENV('GAME_ROOT').'/configs/environment.php';
  require_once GETENV('GAME_ROOT').'/configs/general.php';
  
  $GAME_PDO = new PDO('mysql:dbname='.$GAME_CONFIG['MYSQL_DBNAME'].';host='.$GAME_CONFIG['MYSQL_HOST'].':'.$GAME_CONFIG['MYSQL_PORT'], $GAME_CONFIG['MYSQL_USERNAME'], $GAME_CONFIG['MYSQL_PASSWORD']);

  // ゲームステータスの取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `update_status`
    FROM
      `game_status`;
  ");

  $result     = $statement->execute();
  $gameStatus = $statement->fetch();

  if (!$result || !$gameStatus) {
    echo 'ゲームステータスの取得に失敗しました。処理を終了します。';
    exit;
  }

  if ($gameStatus['update_status']) {
    echo 'すでに更新は確定されています。処理を終了します';
    exit;
  }

  // コンソールからの実行であれば確認処理を行う
  if (php_sapi_name() == 'cli') {
    echo '本当に更新の確定を行いますか？更新の確定を行う場合yesと入力してください。: ';
    $input = trim(fgets(STDIN));
    
    if ($input != 'yes') {
      echo '更新の確定が中止されました。';
      exit;
    }
  }

  // 更新を確定させる
  $statement = $GAME_PDO->prepare("
    UPDATE
      `game_status`
    SET
      `update_status` = true;
  ");

  $result = $statement->execute();  

  if (!$result) {
    echo '更新の確定に失敗しました。';
    exit;
  }

?>