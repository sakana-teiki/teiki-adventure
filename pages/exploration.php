<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('stage', ['non-empty', 'non-negative-integer'])
    ) {
      responseError(400);
    }

    // 探索が実行できるかどうかの判定
    $statement = $GAME_PDO->prepare("
      SELECT
        1
      FROM
        `exploration_stages_master_data`
      WHERE
        (
          `stage_id` = :stage AND
          (
            `requirement_stage_id` IS NULL OR
            `requirement_stage_id` IN (
              SELECT
                `completed_stages`.`stage`
              FROM (
                SELECT
                  `exploration_logs`.`stage`,
                  COUNT(`exploration_logs`.`stage`) AS `clear_count`,
                  `m`.`complete_requirement`
                FROM 
                  `exploration_logs`
                JOIN
                  `exploration_stages_master_data` AS `m` ON `m`.`stage_id` = `exploration_logs`.`stage`
                WHERE
                  `leader` = :ENo
                GROUP BY
                  `exploration_logs`.`stage`
                HAVING
                  `complete_requirement` <= `clear_count`
              ) AS `completed_stages`
            )
          )
        ) AND (
          (SELECT `consumedAP` FROM `characters` WHERE `ENo` = :ENo) < (SELECT `AP` FROM `game_status`)
        );
    ");

    $statement->bindParam(':stage', $_POST['stage']);
    $statement->bindParam(':ENo',   $_SESSION['ENo']);

    $result     = $statement->execute();
    $explorable = $statement->fetch();

    if (!$result) {
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    if (!$explorable) {
      responseError(400); // 探索不可能なステージだった場合は400(Bad Request)を返して処理を中断
    }

    // キャラクター情報の取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `nickname`,
        `ATK`,
        `DEX`,
        `MND`,
        `AGI`,
        `DEF`
      FROM
        `characters`
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result    = $statement->execute();
    $character = $statement->fetch();

    if (!$result || !$character) {
      responseError(500); // SQLの実行や実行結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    }

    // ステージ情報の取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `title`,
        `text`
      FROM
        `exploration_stages_master_data`
      WHERE
        `stage_id` = :stage;
    ");

    $statement->bindParam(':stage', $_POST['stage']);

    $result = $statement->execute();
    $stage  = $statement->fetch();

    if (!$result || !$stage) {
      responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    }

    // ステージのドロップアイテム情報の取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `exploration_stages_master_data_drop_items`.`item`,
        `items_master_data`.`name`,
        `exploration_stages_master_data_drop_items`.`rate_numerator`,
        `exploration_stages_master_data_drop_items`.`rate_denominator`
      FROM
        `exploration_stages_master_data_drop_items`
      JOIN
        `items_master_data` ON `items_master_data`.`item_id` = `exploration_stages_master_data_drop_items`.`item`
      WHERE
        `exploration_stages_master_data_drop_items`.`stage` = :stage AND
        `items_master_data`.`creatable` = true;
    ");

    $statement->bindParam(':stage', $_POST['stage']);

    $result         = $statement->execute();
    $stageDropItems = $statement->fetchAll();

    if (!$result) {
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    }

    // クリア時にドロップするアイテムを計算
    $dropItemsWhenCleared = [];
    foreach ($stageDropItems as $stageDropItem) {
      if (mt_rand(1, $stageDropItem['rate_denominator']) <= $stageDropItem['rate_numerator']) {
        $dropItemsWhenCleared[] = array(
          'item' => $stageDropItem['item'],
          'name' => $stageDropItem['name']
        );
      }
    }

    // 探索ログを出力
    $ENGINES_EXPLORATION['stage']      = $stage;
    $ENGINES_EXPLORATION['character']  = $character;
    $ENGINES_EXPLORATION['drop_items'] = $dropItemsWhenCleared;

    ob_start(); // PHPの実行結果をバッファに出力するように

    require GETENV('GAME_ROOT').'/engines/exploration.php'; // 探索ログエンジンを呼び出し

    $log = ob_get_contents(); // バッファから実行結果を取得
    ob_end_clean(); // バッファへの出力を終了

    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // 消費したAP量の加算
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `consumedAP` = `consumedAP` + 1
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // 探索ログデータの作成
    $statement = $GAME_PDO->prepare("
      INSERT INTO `exploration_logs` (
        `leader`,
        `stage`
      ) VALUES (
        :leader,
        :stage
      );
    ");

    $statement->bindParam(':leader', $_SESSION['ENo']);
    $statement->bindParam(':stage',  $_POST['stage']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    $lastInsertId = intval($GAME_PDO->lastInsertId()); // 登録されたidを取得

    // idをファイル名としてログファイルを保存
    // 1ディレクトリに存在するファイル数が多すぎると速度低下の原因となるため
    // 5桁以上の部分ごとにディレクトリを変更
    // 例：
    //     id=1 : /static/logs/1/1.html
    //     id=2 : /static/logs/1/2.html
    //
    //              …
    //
    //  id=9999 : /static/logs/1/9999.html
    // id=10000 : /static/logs/2/10000.html
    // id=10001 : /static/logs/2/10001.html …

    $directory = strval(floor($lastInsertId/10000) + 1); // ディレクトリ名を計算

    // ディレクトリがなければ作成
    if (!file_exists(GETENV('GAME_ROOT').'/static/logs/'.$directory.'/')) {
      $result = mkdir(GETENV('GAME_ROOT').'/static/logs/'.$directory.'/', 0644, true);

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // ディレクトリの作成に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
    }

    // アイテムの取得処理
    foreach ($dropItemsWhenCleared as $item) {
      // キャラクターの所持アイテム数をアップデート
      $statement = $GAME_PDO->prepare("
        INSERT INTO `characters_items` (
          `ENo`,
          `item`,
          `number`
        ) VALUES (
          :ENo,
          :item,
          1
        )

        ON DUPLICATE KEY UPDATE
          `number` = `number` + 1;
      ");
  
      $statement->bindParam(':ENo',  $_SESSION['ENo']);
      $statement->bindParam(':item', $item['item']);
  
      $result = $statement->execute();
  
      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      // アイテムの排出量をアップデート
      $statement = $GAME_PDO->prepare("
        INSERT INTO `items_yield` (
          `item`,
          `yield`
        ) VALUES (
          :item,
          1
        )

        ON DUPLICATE KEY UPDATE
          `yield` = `yield` + 1
      ");
  
      $statement->bindParam(':item', $item['item']);
  
      $result = $statement->execute();
  
      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
    }

    // ログの結果を書き出し    
    $result = file_put_contents(GETENV('GAME_ROOT').'/static/logs/'.$directory.'/'.$lastInsertId.'.html', $log, LOCK_SH);

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // ファイルの書き込みに失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // ここまで全て成功した場合はコミット
    $GAME_PDO->commit();
  }

  // 現在のAP量を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `consumedAP`,
      (SELECT `AP` FROM `game_status`) AS `distributedAP`
    FROM
      `characters`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();
  $data   = $statement->fetch();

  if (!$result || !$data) {
    // 結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  $AP = $data['distributedAP'] - $data['consumedAP'];

  // 選択可能なステージとその情報を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `stage_id`,
      `title`,
      `complete_requirement`,
      (SELECT COUNT(*) FROM `exploration_logs` WHERE `stage` = `exploration_stages_master_data`.`stage_id` AND `leader` = :ENo) AS `clear_count`
    FROM
      `exploration_stages_master_data`
    WHERE
      `requirement_stage_id` IS NULL OR
      `requirement_stage_id` IN (
        SELECT
          `completed_stages`.`stage`
        FROM (
          SELECT
            `exploration_logs`.`stage`,
            COUNT(`exploration_logs`.`stage`) AS `clear_count`,
            `m`.`complete_requirement`
          FROM 
            `exploration_logs`
          JOIN
            `exploration_stages_master_data` AS `m` ON `m`.`stage_id` = `exploration_logs`.`stage`
          WHERE
            `leader` = :ENo
          GROUP BY
            `exploration_logs`.`stage`
          HAVING
            `complete_requirement` <= `clear_count`
        ) AS `completed_stages`
      );
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();
  $stages = $statement->fetchAll();

  if (!$result || !$stages) {
    // 結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  $PAGE_SETTING['TITLE'] = '探索';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.remaining-ap {
  padding: 20px 20px 0 20px;
  font-weight: bold;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  font-size: 24px;
  color: #666;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>探索</h1>

<section class="remaining-ap">所持AP: <?=$AP?></section>

<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
<section>
  <h2>探索結果</h2>

  <p>
    <?=$stage['title']?>を探索しました。ログは<a href="<?=$GAME_CONFIG['URI']?>logs/<?=$directory.'/'.$lastInsertId.'.html'?>" target="_blank">こちら</a>よりアクセスできます。
  </p>
</section>
<?php } ?>

<section>
  <h2>探索先選択</h2>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

    <section class="form">
      <div class="form-title">探索先</div>
      <select id="input-stage" class="form-input" name="stage">
        <option value="null">▼探索先を選択</option>
<?php foreach ($stages as $stage) { ?>
        <option value="<?=$stage['stage_id']?>"><?=$stage['stage_id']?>. <?=$stage['title']?> (<?=$stage['clear_count']?>/<?=$stage['complete_requirement']?>)</option> 
<?php } ?>
      </select>
    </section>

    <div id="error-message-area"></div>

    <div class="button-wrapper">
      <button class="button" type="submit">探索</button>
    </div>
  </form>
</section>

<script>
<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
  // ログファイルを別タブで開く
  window.open('<?=$GAME_CONFIG['URI']?>logs/<?=$directory.'/'.$lastInsertId.'.html'?>');
<?php } ?>

  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）

  // エラーメッセージを表示する関数及びその関連処理
  var errorMessageArea = $('#error-message-area');
  function showErrorMessage(message) {
    errorMessageArea.empty();
    errorMessageArea.append(
      '<div class="message-banner message-banner-error">'+
        message +
      '</div>'
    );
  }

  $('#form').submit(function(){
    // 値を取得
    var inputStage = $('#input-stage').val();

    // 入力値検証
    // 行き先が選択されていない場合エラーメッセージを表示して送信を中断
    if (inputStage == 'null') {
      showErrorMessage('行き先が選択されていません');
      return false;
    }

    // AP量が0ならエラーメッセージを表示して送信を中断
    if (<?=$AP?> == 0) {
      showErrorMessage('APが残っていません');
      return false;
    }

    // レスポンス待ちの場合アラートを表示して送信を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 上記のどれにも当てはまらない場合送信が行われるためレスポンス待ちをONに
    waitingResponse = true;
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>