<?php
  putenv('GAME_ROOT='.dirname(__DIR__));
  
  require_once GETENV('GAME_ROOT').'/configs/environment.php';
  require_once GETENV('GAME_ROOT').'/configs/general.php';
  
  $GAME_PDO = new PDO('mysql:dbname='.$GAME_CONFIG['MYSQL_DBNAME'].';host='.$GAME_CONFIG['MYSQL_HOST'].':'.$GAME_CONFIG['MYSQL_PORT'], $GAME_CONFIG['MYSQL_USERNAME'], $GAME_CONFIG['MYSQL_PASSWORD']);

  // ゲームステータスの取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `update_status`,
      `next_update_nth`
    FROM
      `game_status`;
  ");

  $result     = $statement->execute();
  $gameStatus = $statement->fetch();

  if (!$result || !$gameStatus) {
    echo 'ゲームステータスの取得に失敗しました。処理を終了します。';
    exit;
  }

  // コンソールからの実行であれば確認処理を行う
  if (php_sapi_name() == 'cli') {
    if ($gameStatus['update_status']) {
      echo '本当に更新を行いますか？更新を行う場合yesと入力してください。: ';
    } else {
      echo '本当に更新を行いますか？更新が確定されていないため、再更新となります。再更新を行う場合yesと入力してください。: ';
    }
    $input = trim(fgets(STDIN));
    
    if ($input != 'yes') {
      echo '更新が中止されました。';
      exit;
    }
  }

  // 更新確定状態なら次回の更新回数が、更新未確定状態なら今回の更新回数が更新対象の回数となる
  $target_nth = $gameStatus['update_status'] ? $gameStatus['next_update_nth'] : $gameStatus['next_update_nth'] - 1;

  // キャラクターの行動宣言の取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `characters_declarations`.`ENo`,
      `characters_declarations`.`diary`
    FROM
      `characters_declarations`
    JOIN
      `characters` ON `characters`.`ENo` = `characters_declarations`.`ENo`
    WHERE
      `characters_declarations`.`nth` = :nth AND
      `characters`.`deleted` = false;
  ");

  $statement->bindParam(':nth', $target_nth, PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    echo 'キャラクターの行動宣言の取得に失敗しました。処理を終了します。';
    exit;
  }

  $declarations = $statement->fetchAll();

  // 行動宣言ごとに処理
  foreach ($declarations as $declaration) {
    // 行動内容に応じた結果の生成

    $ENGINES_STORY['diary'] = $declaration['diary'];

    ob_start(); // PHPの実行結果をバッファに出力するように

    require GETENV('GAME_ROOT').'/engines/story.php'; // 探索ログエンジンを呼び出し

    $log = ob_get_contents(); // バッファから実行結果を取得
    ob_end_clean(); // バッファへの出力を終了

    // 結果の保存
    // ディレクトリがなければ作成
    if (!file_exists(GETENV('GAME_ROOT').'/static/results/'.$target_nth.'/')) {
      $result = mkdir(GETENV('GAME_ROOT').'/static/results/'.$target_nth.'/', 0644, true);

      if (!$result) {
        echo '保存ディレクトリの作成に失敗しました。処理を終了します。';
        exit;
      }
    }

    // 結果を書き出し    
    $result = file_put_contents(GETENV('GAME_ROOT').'/static/results/'.$target_nth.'/'.$declaration['ENo'].'.html', $log, LOCK_SH);

    if (!$result) {
      echo '結果の書き出しに失敗しました。処理を終了します。';
      exit;
    }
  }

  // 更新確定状態なら更新回数を+1し、更新未確定状態へ変更
  if ($gameStatus['update_status']) {
    $statement = $GAME_PDO->prepare("
      UPDATE
        `game_status`
      SET
        `update_status`   = false,
        `next_update_nth` = `next_update_nth` + 1;
    ");

    $result = $statement->execute();  

    if (!$result) {
      echo 'ゲームステータスのアップデートに失敗しました。';
      exit;
    }
  }
  
?>