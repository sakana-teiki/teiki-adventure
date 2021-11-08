<?php
  require_once GETENV('GAME_ROOT').'/configs/environment.php';
  require_once GETENV('GAME_ROOT').'/configs/general.php';
  
  $GAME_PDO = new PDO('mysql:dbname='.$GAME_CONFIG['MYSQL_DBNAME'].';host='.$GAME_CONFIG['MYSQL_HOST'].':'.$GAME_CONFIG['MYSQL_PORT'], $GAME_CONFIG['MYSQL_USERNAME'], $GAME_CONFIG['MYSQL_PASSWORD']);

  // コンソールからの実行であれば確認処理を行う
  if (php_sapi_name() == 'cli') {
    echo '本当にマスタデータのエクスポートを行いますか？エクスポートを行う場合yesと入力してください。: ';
    $input = trim(fgets(STDIN));
    
    if ($input != 'yes') {
      echo 'エクスポート処理が中止されました。';
      exit;
    }
  }

  function exportMasterData($table) {
    global $GAME_PDO;

    // 指定テーブルのマスタデータを取得
    $statement = $GAME_PDO->prepare("
      SELECT
        *
      FROM
        `$table`;
    ");
    
    $result = $statement->execute();

    if (!$result) {
      echo $table.'の取得時にエラーが発生しました。';
      exit;
    }

    $masters = $statement->fetchAll();

    if ($masters) {
      $lines   = count($masters);
      $columns = count($masters[0]) / 2; // PDOは数字によるキーとカラム名によるキーを出力するためカラム数は/2したものになる。

      for ($i = 0; $i < $lines; $i++) {
        unset($masters[$i]["id"]); // idカラムを削除

        for ($j = 0; $j < $columns; $j++) {
          unset($masters[$i][$j]); // 数字によるキーを削除
        }

        // exportsディレクトリがなければ作成
        if (!file_exists(GETENV('GAME_ROOT').'/masters/exports/')) {
          mkdir(GETENV('GAME_ROOT').'/masters/exports/'); 
        }
        
        file_put_contents(GETENV('GAME_ROOT').'/masters/exports/'.$table.'.json', json_encode($masters, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_SH);
        echo $table.'の出力が完了しました。';
      }
    } else {
      echo $table.'にはデータが存在しなかったためスキップされました。';
    }
  }

  // 全てのテーブル名を取得  
  $statement = $GAME_PDO->prepare("
    SHOW TABLES;
  ");

  $result = $statement->execute();

  if (!$result) { // 失敗した場合は500(Internal Server Error)を返して処理を中断
    responseError(500);
  }

  $tables = $statement->fetchAll();

  // 設定でマスタデータに指定されたものをエクスポート
  foreach ($GAME_CONFIG['MASTER_DATA_TABLES'] as $table) {
    exportMasterData($table);
  }  

  echo "全てのマスタデータの出力が完了しました。";

?>