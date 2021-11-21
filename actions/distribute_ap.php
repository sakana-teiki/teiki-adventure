<?php
  putenv('GAME_ROOT='.dirname(__DIR__));
  
  require_once GETENV('GAME_ROOT').'/configs/environment.php';
  require_once GETENV('GAME_ROOT').'/configs/general.php';
  
  $GAME_PDO = new PDO('mysql:dbname='.$GAME_CONFIG['MYSQL_DBNAME'].';host='.$GAME_CONFIG['MYSQL_HOST'].':'.$GAME_CONFIG['MYSQL_PORT'], $GAME_CONFIG['MYSQL_USERNAME'], $GAME_CONFIG['MYSQL_PASSWORD']);

  // ゲームステータスの取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `distributing_ap`
    FROM
      `game_status`;
  ");

  $statement->bindParam(':ap', 1, PDO::PARAM_INT);

  $result     = $statement->execute();
  $gameStatus = $statement->fetch();

  if (!$result || !$gameStatus) {
    echo 'ゲームステータスの取得に失敗しました。処理を終了します。';
    exit;
  } else if (!$gameStatus['distributing_ap']) {
    echo '自動AP配布を行わない設定になっています。処理を終了します。';
    exit;
  } else {
    // 配布AP量に1加算
    $statement = $GAME_PDO->prepare("
      UPDATE
        `game_status`
      SET
        `AP` = `AP` + :ap;
    ");

    $statement->bindParam(':ap', 1, PDO::PARAM_INT);

    $result = $statement->execute();

    if (!$result) {
      echo 'APの配布処理中にエラーが発生しました。処理を終了します。';
      exit;
    } else {
      echo 'APの配布が完了しました。処理を終了します。';
      exit;
    }
  }

?>