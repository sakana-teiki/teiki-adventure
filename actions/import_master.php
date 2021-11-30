<?php
  putenv('GAME_ROOT='.dirname(__DIR__));

  // .htaccessでSetEnvが使えず$_SERVER['DOCUMENT_ROOT']を使用するよう変更した場合用に、コマンドライン等からDOCUMENT_ROOTを設定できるように
  // GAME_ROOTをDOCUMENT_ROOTを使用する形に置換していないのであれば特に気にする必要はありません
  // 書式：php filename.php --document-root="path"
  if (isset($argv)) {
    foreach ($argv as $arg) {
      if (strpos($arg, '--document-root=') === 0) {
        $_SERVER['DOCUMENT_ROOT'] = substr($arg, strlen('--document-root='));
      }
    }
  }
  
  require_once GETENV('GAME_ROOT').'/configs/environment.php';
  require_once GETENV('GAME_ROOT').'/configs/general.php';
  
  $GAME_PDO = new PDO('mysql:dbname='.$GAME_CONFIG['MYSQL_DBNAME'].';host='.$GAME_CONFIG['MYSQL_HOST'].':'.$GAME_CONFIG['MYSQL_PORT'], $GAME_CONFIG['MYSQL_USERNAME'], $GAME_CONFIG['MYSQL_PASSWORD']);

  // コンソールからの実行であれば確認処理を行う
  if (php_sapi_name() == 'cli') {
    echo '本当にマスタデータのインポートを行いますか？インポートを行う場合yesと入力してください。: ';
    $input = trim(fgets(STDIN));
    
    if ($input != 'yes') {
      echo 'インポート処理が中止されました。';
      exit;
    }
  }

  // 外部キー制約の対象となるカラムを含むテーブルにマスタデータを取り込む関数
  function importMasterDataContainsForeignKey($table, $foreignKey) {
    global $GAME_PDO;
    
    // マスタデータの読み込み
    $mastersJson = file_get_contents(GETENV('GAME_ROOT').'/masters/datas/'.$table.'.json');
    $masters = json_decode($mastersJson, true);

    // トランザクション開始
    $GAME_PDO->beginTransaction();

    foreach ($masters as $master) {
      // キーのリストを取得
      $keys = array_keys($master);

      // 外部キー制約のついたキーを除外したキーのリストを取得
      $keysExcludedForeignKey = array_diff($keys, array($foreignKey)); // $keysから$foreignKeyを除外
      $keysExcludedForeignKey = array_values($keysExcludedForeignKey); // array_diffは番号を振り直さないため番号を振り直す

      // SQL文の組み立て
      // 以下のようなSQLが組み立てられます
      // INSERT INTO `items_master_data` (`item_id`, `name`, `description`, `price`, `shop`, `tradable`, `usable`, `relinquishable`, `createable`, `category`) VALUES (:item_id, :name, :description, :price, :shop, :tradable, :usable, :relinquishable, :createable, :category) ON DUPLICATE KEY UPDATE `name` = :name, `description` = :description, `price` = :price, `shop` = :shop, `tradable` = :tradable, `usable` = :usable, `relinquishable` = :relinquishable, `createable` = :createable, `category` = :category;
      $sql = "INSERT INTO `$table` (";

      for ($i = 0; $i < count($keys); $i++) {
        $sql .= '`'.$keys[$i].'`';
        
        // 最後のキーでなければカンマを追加
        if ($i !== count($keys) - 1) {
          $sql .= ', ';
        }
      }

      $sql .= ") VALUES (";

      for ($i = 0; $i < count($keys); $i++) {
        $sql .= ':'.$keys[$i];
        
        // 最後のキーでなければカンマを追加
        if ($i !== count($keys) - 1) {
          $sql .= ', ';
        }
      }

      $sql .= ") ON DUPLICATE KEY UPDATE ";

      for ($i = 0; $i < count($keysExcludedForeignKey); $i++) {
        $sql .= '`'.$keysExcludedForeignKey[$i].'` = :'.$keysExcludedForeignKey[$i];
        
        // 最後のキーでなければカンマを追加
        if ($i !== count($keysExcludedForeignKey) - 1) {
          $sql .= ', ';
        }
      }

      $sql .= ";";

      // プリペアドステートメントの準備
      $statement = $GAME_PDO->prepare($sql);

      // プリペアドステートメントの割り当て
      foreach ($keys as $key) {
        $statement->bindParam(':'.$key, $master[$key]);
      }

      // SQLの実行
      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        echo $table.'の取り込み時にエラーが発生しました。';
        exit;
      }
    }

    // トランザクション完了
    $GAME_PDO->commit();
  }

  // 外部キー制約の対象となるカラムを含まないテーブルにマスタデータを取り込む関数
  function importMasterDataNotContainsForeignKey($table) {
    global $GAME_PDO;

    // マスタデータの読み込み
    $mastersJson = file_get_contents(GETENV('GAME_ROOT').'/masters/datas/'.$table.'.json');
    $masters = json_decode($mastersJson, true);

    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // 指定テーブルのデータを全件削除
    $statement = $GAME_PDO->prepare("DELETE FROM `$table`;");
    $result    = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      echo $table.'の初期化時にエラーが発生しました。';
      exit;
    }

    // 指定テーブルのデータを登録
    if (!is_null($masters)) {
      foreach ($masters as $master) {
        // キーのリストを取得
        $keys = array_keys($master);

        // SQL文の組み立て
        // 以下のようなSQLが組み立てられます
        // INSERT INTO `items_master_data_effects` (`item`, `effect`, `value`) VALUES (:item, :effect, :value);
        $sql = "INSERT INTO `$table` (";

        for ($i = 0; $i < count($keys); $i++) {
          $sql .= '`'.$keys[$i].'`';
          
          // 最後のキーでなければカンマを追加
          if ($i !== count($keys) - 1) {
            $sql .= ', ';
          }
        }

        $sql .= ") VALUES (";

        for ($i = 0; $i < count($keys); $i++) {
          $sql .= ':'.$keys[$i];
          
          // 最後のキーでなければカンマを追加
          if ($i !== count($keys) - 1) {
            $sql .= ', ';
          }
        }

        $sql .= ");";

        // プリペアドステートメントの準備
        $statement = $GAME_PDO->prepare($sql);

        // プリペアドステートメントの割り当て
        foreach ($keys as $key) {
          $statement->bindParam(':'.$key, $master[$key]);
        }

        // SQLの実行
        $result = $statement->execute();

        if (!$result) {
          $GAME_PDO->rollBack();
          echo $table.'の取り込み時にエラーが発生しました。';
          exit;
        }
      }
    }

    // トランザクション完了
    $GAME_PDO->commit();
  }

  // 外部キー制約のあるカラムを持つテーブルから処理
  foreach ($GAME_CONFIG['MASTER_DATA_TABLES_CONTAINS_FOREIGN_KEY'] as $table) {
    importMasterDataContainsForeignKey($table['name'], $table['foreign_key']);
  }

  // 外部キー制約のないテーブルを抽出
  // 外部キー制約のあるテーブルの一覧を取得
  foreach ($GAME_CONFIG['MASTER_DATA_TABLES_CONTAINS_FOREIGN_KEY'] as $table) {
    $tablesContainsForeignKey[] = $table['name'];
  }

  // マスタデータテーブル一覧から外部キー制約のあるテーブルを除外
  $tablesNotContainsForeignKey = array_diff($GAME_CONFIG['MASTER_DATA_TABLES'], $tablesContainsForeignKey); 

  // 外部キー制約のないテーブルを処理
  foreach ($tablesNotContainsForeignKey as $table) {
    importMasterDataNotContainsForeignKey($table);
  }
?>