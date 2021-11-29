<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/notification.php';
  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['type'])) { // 受け取ったデータにtypeがなければ400(Bad Request)を返して処理を中断
      responseError(400);
    }

    // typeごとに処理を振り分け
    if ($_POST['type'] == 'send') {
      // アイテム送付
      // 入力値検証
      if (
        !validatePOST('item',   ['non-empty', 'non-negative-integer']) ||
        !validatePOST('target', ['non-empty', 'natural-number'])       ||
        !validatePOST('number', ['non-empty', 'natural-number'])
      ) {
        responseError(400);
      }

      if ($_POST['target'] == $_SESSION['ENo']) {
        responseError(400); // 自分自身に送付しようとしていた場合は400(Forbidden)を返して処理を中断
      }

      // 送付することのできるアイテムか、及び送付先のキャラクターが削除されておらずブロック/被ブロックの関係でないかを判定
      $statement = $GAME_PDO->prepare("
        SELECT
          1
        FROM
          `items_master_data`
        WHERE
          `item_id`  = :item AND
          `tradable` = true  AND
          EXISTS (
            SELECT
              *
            FROM
              `characters`
            WHERE
              `ENo`     = :target AND
              `deleted` = false
          ) AND
          NOT EXISTS (
            SELECT
              *
            FROM
              `characters_blocks`
            WHERE
              (`blocker` = :ENo AND `blocked` = :target) OR
              (`blocked` = :ENo AND `blocker` = :target)
          );
      ");

      $statement->bindParam(':item',   $_POST['item'],   PDO::PARAM_INT);
      $statement->bindParam(':target', $_POST['target'], PDO::PARAM_INT);
      $statement->bindParam(':ENo',    $SESSION['ENo'],  PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      $tradable = $statement->fetch();

      if (!$tradable) {
        responseError(403); // 送付できない場合は403(Forbidden)を返して処理を中断
      }

      // トランザクション開始
      $GAME_PDO->beginTransaction();

      // アイテムの所持数を更新
      $statement = $GAME_PDO->prepare("
        UPDATE
          `characters_items`
        SET
          `number` = `number` - :number
        WHERE
          `ENo`  = :ENo  AND
          `item` = :item;
      ");

      $statement->bindParam(':ENo',    $_SESSION['ENo'], PDO::PARAM_INT);
      $statement->bindParam(':item',   $_POST['item'],   PDO::PARAM_INT);
      $statement->bindParam(':number', $_POST['number'], PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      // 所持数が0になったアイテムは削除する
      $statement = $GAME_PDO->prepare("
        DELETE FROM
          `characters_items`
        WHERE
          `ENo`    = :ENo AND
          `number` = 0;
      ");

      $statement->bindParam(':ENo', $_SESSION['ENo'], PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      // アイテム送付リクエストを作成
      $statement = $GAME_PDO->prepare("
        INSERT INTO `trades` (
          `master`,
          `target`,
          `item`,
          `number`,
          `state`
        ) VALUES (
          :ENo,
          :target,
          :item,
          :number,
          'trading'
        );
      ");

      $statement->bindParam(':ENo',    $_SESSION['ENo'], PDO::PARAM_INT);
      $statement->bindParam(':target', $_POST['target'], PDO::PARAM_INT);
      $statement->bindParam(':item',   $_POST['item'],   PDO::PARAM_INT);
      $statement->bindParam(':number', $_POST['number'], PDO::PARAM_INT);
      
      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      // 送付先の通知情報を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `notification_trade`,
          `notification_webhook_trade`,
          `webhook`
        FROM
          `characters`
        WHERE
          `ENo` = :ENo;
      ");

      $statement->bindParam(':ENo', $_POST['target'], PDO::PARAM_INT);

      $result = $statement->execute();
      $target = $statement->fetch();

      if (!$result || !$target) {
        $GAME_PDO->rollBack();
        responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      if ($target['notification_trade']) {
        // 送付先のアイテムトレード通知が有効なら通知を作成
        $statement = $GAME_PDO->prepare("
          INSERT INTO `notifications` (
            `ENo`,
            `type`,
            `target`,
            `message`
          ) VALUES (
            :ENo,
            'trade_start',
            :target,
            ''
          );
        ");
  
        $statement->bindParam(':ENo',    $target['ENo']);
        $statement->bindParam(':target', $_POST['id']);
  
        $result = $statement->execute();
      }

      if ($target['webhook'] && $target['notification_webhook_trade']) {
        // 送付先のWebhookが入力されており、Discordのアイテムトレード通知が有効なら通知を送信
        // 自分自身のニックネームを取得
        $statement = $GAME_PDO->prepare("
          SELECT
            `characters`.`nickname`
          FROM
            `characters`
          WHERE
            `ENo` = :user;
        ");
  
        $statement->bindParam(':user', $_SESSION['ENo']);
  
        $result = $statement->execute();
        $nickname = $statement->fetch();
  
        notifyDiscord($target['webhook'], 'ENo.'.$_SESSION['ENo'].' '.$nickname['nickname'].'からアイテムが送付されました。 '.$GAME_CONFIG['ABSOLUTE_URI'].'trade');
      }

      // ここまで全て成功した場合はコミット
      $GAME_PDO->commit();
    } else if ($_POST['type'] == 'accept') {
      // アイテム受領
      // 入力値検証
      if (!validatePOST('id', ['non-empty', 'natural-number'])) {
        responseError(400);
      }
      
      // トランザクション開始
      $GAME_PDO->beginTransaction();

      // 対象のトレード情報と通知情報を取得、同時にこれが自分に送られたアイテムか、送付元のキャラクターが削除されていないか、既に終了したトレードでないかを判定
      $statement = $GAME_PDO->prepare("
        SELECT
          `trades`.`item`,
          `trades`.`number`,
          `characters`.`ENo` AS `master`,
          `characters`.`notification_trade`,
          `characters`.`notification_webhook_trade`,
          `characters`.`webhook`
        FROM
          `trades`
        JOIN
          `characters` ON `characters`.`ENo` = `trades`.`master`
        WHERE
          `trades`.`id`          = :id       AND
          `trades`.`target`      = :ENo      AND
          `trades`.`state`       = 'trading' AND
          `characters`.`deleted` = false;
      ");

      $statement->bindParam(':id',  $_POST['id'],     PDO::PARAM_INT);
      $statement->bindParam(':ENo', $_SESSION['ENo'], PDO::PARAM_INT);
      
      $result = $statement->execute();
      $trade  = $statement->fetch();

      if (!$result || !$trade) {
        $GAME_PDO->rollBack();
        responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      // トレードの状態を更新
      $statement = $GAME_PDO->prepare("
        UPDATE
          `trades`
        SET
          `state` = 'finished'
        WHERE
          `id` = :id;
      ");

      $statement->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
      
      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      // アイテムの取得処理
      $statement = $GAME_PDO->prepare("
        INSERT INTO `characters_items` (
          `ENo`,
          `item`,
          `number`
        ) VALUES (
          :ENo,
          :item,
          :number
        )
  
        ON DUPLICATE KEY UPDATE
          `number` = `number` + :number;
      ");
    
      $statement->bindParam(':ENo',    $_SESSION['ENo'], PDO::PARAM_INT);
      $statement->bindParam(':item',   $trade['item'],   PDO::PARAM_INT);
      $statement->bindParam(':number', $trade['number'], PDO::PARAM_INT);
    
      $result = $statement->execute();
    
      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      if ($trade['notification_trade']) {
        // 送付元のアイテムトレード通知が有効なら通知を作成
        $statement = $GAME_PDO->prepare("
          INSERT INTO `notifications` (
            `ENo`,
            `type`,
            `target`,
            `message`
          ) VALUES (
            :ENo,
            'trade_finish',
            :target,
            ''
          );
        ");
  
        $statement->bindParam(':ENo',    $trade['master']);
        $statement->bindParam(':target', $_POST['id']);
  
        $result = $statement->execute();
      }

      if ($trade['webhook'] && $trade['notification_webhook_trade']) {
        // 送付元のWebhookが入力されており、Discordのアイテムトレード通知が有効なら通知を送信
        // 自分自身のニックネームを取得
        $statement = $GAME_PDO->prepare("
          SELECT
            `characters`.`nickname`
          FROM
            `characters`
          WHERE
            `ENo` = :user;
        ");
  
        $statement->bindParam(':user', $_SESSION['ENo']);
  
        $result = $statement->execute();
        $nickname = $statement->fetch();
  
        notifyDiscord($trade['webhook'], 'ENo.'.$_SESSION['ENo'].' '.$nickname['nickname'].'はあなたが送付したアイテムを受領しました。 '.$GAME_CONFIG['ABSOLUTE_URI'].'trade/history');
      }
  
      // ここまで全て成功した場合はコミット
      $GAME_PDO->commit();
    } else if ($_POST['type'] == 'decline') {
      // アイテム辞退
      // 入力値検証
      if (!validatePOST('id', ['non-empty', 'natural-number'])) {
        responseError(400);
      }
      
      // トランザクション開始
      $GAME_PDO->beginTransaction();

      // 対象のトレード情報と通知情報を取得、同時にこれが自分に送られたアイテムか、送付元のキャラクターが削除されていないか、既に終了したトレードでないかを判定
      $statement = $GAME_PDO->prepare("
        SELECT
          `trades`.`item`,
          `trades`.`number`,
          `characters`.`ENo` AS `master`,
          `characters`.`notification_trade`,
          `characters`.`notification_webhook_trade`,
          `characters`.`webhook`
        FROM
          `trades`
        JOIN
          `characters` ON `characters`.`ENo` = `trades`.`master`
        WHERE
          `trades`.`id`          = :id       AND
          `trades`.`target`      = :ENo      AND
          `trades`.`state`       = 'trading' AND
          `characters`.`deleted` = false;
      ");

      $statement->bindParam(':id',  $_POST['id'],     PDO::PARAM_INT);
      $statement->bindParam(':ENo', $_SESSION['ENo'], PDO::PARAM_INT);
      
      $result = $statement->execute();
      $trade  = $statement->fetch();

      if (!$result || !$trade) {
        $GAME_PDO->rollBack();
        responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      // トレードの状態を更新
      $statement = $GAME_PDO->prepare("
        UPDATE
          `trades`
        SET
          `state` = 'declined'
        WHERE
          `id` = :id;
      ");

      $statement->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
      
      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      // 送付元のアイテムの取得処理
      $statement = $GAME_PDO->prepare("
        INSERT INTO `characters_items` (
          `ENo`,
          `item`,
          `number`
        ) VALUES (
          :master,
          :item,
          :number
        )
  
        ON DUPLICATE KEY UPDATE
          `number` = `number` + :number;
      ");
    
      $statement->bindParam(':master', $trade['master'], PDO::PARAM_INT);
      $statement->bindParam(':item',   $trade['item'],   PDO::PARAM_INT);
      $statement->bindParam(':number', $trade['number'], PDO::PARAM_INT);
    
      $result = $statement->execute();
    
      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      if ($trade['notification_trade']) {
        // 送付元のアイテムトレード通知が有効なら通知を作成
        $statement = $GAME_PDO->prepare("
          INSERT INTO `notifications` (
            `ENo`,
            `type`,
            `target`,
            `message`
          ) VALUES (
            :ENo,
            'trade_decline',
            :target,
            ''
          );
        ");
  
        $statement->bindParam(':ENo',    $trade['master']);
        $statement->bindParam(':target', $_POST['id']);
  
        $result = $statement->execute();
      }

      if ($trade['webhook'] && $trade['notification_webhook_trade']) {
        // 送付元のWebhookが入力されており、Discordのアイテムトレード通知が有効なら通知を送信
        // 自分自身のニックネームを取得
        $statement = $GAME_PDO->prepare("
          SELECT
            `characters`.`nickname`
          FROM
            `characters`
          WHERE
            `ENo` = :user;
        ");
  
        $statement->bindParam(':user', $_SESSION['ENo']);
  
        $result = $statement->execute();
        $nickname = $statement->fetch();
  
        notifyDiscord($trade['webhook'], 'ENo.'.$_SESSION['ENo'].' '.$nickname['nickname'].'はあなたが送付したアイテムを辞退しました。 '.$GAME_CONFIG['ABSOLUTE_URI'].'trade/history');
      }
  
      // ここまで全て成功した場合はコミット
      $GAME_PDO->commit();
    } else { 
      // 上記のどれでもなければ400(Bad Request)を返して処理を中断
      responseError(400);
    }
  }

  // 送信主キャラクターが未削除の未受領のアイテム一覧を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `trades`.`id`,
      `trades`.`master`,
      `characters_icons`.`url` AS `master_icon`,
      `items_master_data`.`name`,
      `trades`.`number`,
      `trades`.`sended_at`
    FROM
      `trades`
    JOIN
      `items_master_data` ON `items_master_data`.`item_id` = `trades`.`item`
    JOIN
      `characters` ON `characters`.`ENo` = `trades`.`master`
    LEFT JOIN
      `characters_icons` ON `characters_icons`.`ENo` = `trades`.`master`
    WHERE
      `trades`.`target` = :ENo      AND
      `trades`.`state`  = 'trading' AND
      `characters`.`deleted` = false;
  ");

  $statement->bindValue(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  $receivableItems = $statement->fetchAll();

  // 所持している送付可能アイテムを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `items_master_data`.`item_id`,
      `items_master_data`.`name`,
      `items_master_data`.`description`,
      `items_master_data`.`tradable`,
      `items_master_data`.`category`,
      `characters_items`.`number`
    FROM
      `characters_items`
    JOIN
      `items_master_data` ON `items_master_data`.`item_id` = `characters_items`.`item`
    WHERE
      `characters_items`.`ENo`       = :ENo AND
      `items_master_data`.`tradable` = true
    ORDER BY
      `items_master_data`.`item_id`;
  ");

  $statement->bindValue(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  $items = $statement->fetchAll();

  $PAGE_SETTING['TITLE'] = 'アイテムトレード';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.receivable-items {
  border-collapse: collapse;
  margin: 0 auto 0 0;
  font-size: 15px;
}

.receivable-items th {
  background-color: #444;
  border: 1px solid #F8F8F8;
  color: #EEE;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  height: 30px;
}

.receivable-items td {
  border: 1px solid #F8F8F8;
  border-bottom: 1px solid lightgray;
  height: 55px;
}

.receivable-items th:nth-child(2), .receivable-items td:nth-child(2) {
  text-align: center;
  width: 150px;
}

.receivable-items td:nth-child(2) {
  color: #444;
  font-weight: bold;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
}

.receivable-items th:nth-child(3), .receivable-items td:nth-child(3) {
  text-align: center;
  width: 40px;
}

.receivable-items th:nth-child(4), .receivable-items td:nth-child(4) {
  text-align: center;
  width: 180px;
}

.receivable-items th:nth-child(5), .receivable-items td:nth-child(5) {
  text-align: center;
  width: 60px;
}

.receivable-items th:nth-child(6), .receivable-items td:nth-child(6) {
  text-align: center;
  width: 60px;
}

.items {
  border-collapse: collapse;
  margin: 0 auto;
  font-size: 15px;
  width: 100%;
}

.items th {
  background-color: #444;
  border: 1px solid #F8F8F8;
  color: #EEE;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  height: 30px;
}

.items td {
  border: 1px solid #F8F8F8;
  border-bottom: 1px solid lightgray;
  height: 55px;
}

.items th:nth-child(1), .items td:nth-child(1) {
  text-align: center;
  width: 150px;
}

.items td:nth-child(1) {
  color: #444;
  font-weight: bold;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
}

.items th:nth-child(2), .items td:nth-child(2) {
  text-align: center;
  width: 40px;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>アイテムトレード</h1>

<div class="button-link-wrapper">
  <a href="<?=$GAME_CONFIG['URI']?>trade/history">トレード履歴</a>
</div>

<section>
  <h2>アイテム受領</h2>

<?php if (!$receivableItems) { ?>
  <p>
    未受領のアイテムはありません。
  </p>
<?php } else { ?>
  <table class="receivable-items">
    <thead>
      <tr>
        <th>送付元</th>
        <th>アイテム</th>
        <th>個数</th>
        <th>送付日時</th>
        <th>受領</th>
        <th>辞退</th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($receivableItems as $item) { ?>
      <tr>
        <td>
          <a href="<?=$GAME_CONFIG['URI']?>profile?ENo=<?=$item['master']?>" target="_blank">
            <?php
              $COMPONENT_ICON['src'] = $item['master_icon'];
              include GETENV('GAME_ROOT').'/components/icon.php';
            ?>
          </a>
        </td>
        <td>
          <?=$item['name']?>
        </td>
        <td>
          <?=$item['number']?>
        </td>
        <td>
          <?=$item['sended_at']?>
        </td>
        <td>
          <form class=".accept-form" method="post">
            <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
            <input type="hidden" name="type" value="accept">
            <input type="hidden" name="id" value="<?=$item['id']?>">

            <button>
              受領
            </button>
          </form>
        </td>
        <td>
          <form class=".decline-form" method="post">
            <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
            <input type="hidden" name="type" value="decline">
            <input type="hidden" name="id" value="<?=$item['id']?>">

            <button>
              辞退
            </button>
          </form>
        </td>
      </tr>
<?php } ?>
    </tbody>
  </table>
<?php } ?>
</section>

<section>
  <h2>所持送付可能アイテム</h2>

  <table class="items">
    <thead>
      <tr>
        <th>アイテム名</th>
        <th>数</th>
        <th>アイテム説明</th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($items as $item) { ?>
      <tr>
        <td>
          <?= $item['name'] ?>
        </td>
        <td>
          <?= $item['number'] ?>
        </td>
        <td>
          <?= $item['description'] ?>
        </td>
      </tr>
<?php } ?>
    </tbody>
  </table>
</section>

<section>
  <h2>送付</h2>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input type="hidden" name="type" value="send">
    
    <section class="form">
      <div class="form-title">ENo</div>
      <div class="form-description">アイテム送付先キャラクターのENoを指定します。</div>
      <input id="input-eno" class="form-input" type="number" name="target" placeholder="ENo">
      <button id="check-eno" type="button" class="form-check-button">対象を確認</button>
    </section>

    <section class="form">
      <div class="form-title">送付アイテム</div>
      <div class="form-description">送付するアイテムを選択します。</div>
      <select id="input-item" class="form-input" name="item">
        <option value="null">▼送付アイテム選択</option>
<?php foreach ($items as $item) { ?>
        <option value="<?=$item['item_id']?>"><?=$item['name']?> (<?= $item['number'] ?>個所持)</option>
<?php } ?>
      </select>
    </section>

    <section class="form">
      <div class="form-title">送付数</div>
      <div class="form-description">アイテムの送付数を指定します。</div>
      <input id="input-number" class="form-input" type="number" name="number" placeholder="送付数" min="1">
    </section>

    <div id="error-message-area"></div>

    <div class="button-wrapper">
      <button class="button" type="submit">送付</button>
    </div>
  </form>
</section>

<script>
  // 送信先を確認ボタンを押したら新しいタブで指定のENoのプロフィールページを表示
  $('#check-eno').on('click', function(){
    window.open('<?=$GAME_CONFIG['URI']?>profile?ENo=' + $('#input-eno').val());
  });

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
    var inputENo    = $('#input-eno').val();
    var inputItem   = $('#input-item').val();
    var inputNumber = $('#input-number').val();

    // 入力値検証
    // ENoが入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputENo) {
      showErrorMessage('ENoが入力されていません');
      return false;
    }

    // 送付アイテムが選択されていない場合エラーメッセージを表示して送信を中断
    if (inputItem == 'null') {
      showErrorMessage('送付アイテムが選択されていません');
      return false;
    }

    // 送付数が入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputNumber || Number(inputNumber) == 0) {
      showErrorMessage('送付数が入力されていません。');
      return false;
    }

    // 送付数の入力形式が不正な場合エラーメッセージを表示して処理を中断
    if (!/^[1-9][0-9]*$/.test(inputNumber)) {
      showErrorMessage('送付数の入力形式が不正です');
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