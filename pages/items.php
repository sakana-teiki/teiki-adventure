<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';
  require_once GETENV('GAME_ROOT').'/utils/parser.php';

  require_once GETENV('GAME_ROOT').'/masters/logics/items.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['type'])) { // 受け取ったデータにtypeがなければ400(Bad Request)を返して処理を中断
      responseError(400);
    }

    // typeごとに処理を振り分け
    if ($_POST['type'] == 'relinquish') {
      // 破棄処理の場合
      // 入力値検証
      if (
        !validatePOST('item',   ['non-empty', 'non-negative-integer']) ||
        !validatePOST('number', ['non-empty', 'natural-number'])
      ) {
        responseError(400);
      }

      // 捨てられるアイテムかどうかを判定
      $statement = $GAME_PDO->prepare("
        SELECT
          1
        FROM
          `items_master_data`
        WHERE
          `item_id`        = :item AND
          `relinquishable` = true;
      ");
  
      $statement->bindParam(':item', $_POST['item'], PDO::PARAM_INT);

      $result = $statement->execute();
  
      if (!$result) {
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      $relinquishable = $statement->fetch();

      if (!$relinquishable) {
        responseError(403); // 捨てられないアイテムだった場合は403(Forbidden)を返して処理を中断
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

      // ここまで全て成功した場合はコミット
      $GAME_PDO->commit();
    } else if ($_POST['type'] == 'use') {
      // 使用処理の場合
      // 入力値検証
      if (!validatePOST('item',   ['non-empty', 'non-negative-integer'])) {
        responseError(400);
      }

      // アイテムの情報を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `items_master_data`.`item_id`,
          `items_master_data`.`usable`,
          IFNULL(GROUP_CONCAT(`items_master_data_effects`.`effect`, '\n', `items_master_data_effects`.`value` SEPARATOR '\n'), '') AS `effects`
        FROM
          `items_master_data`
        LEFT JOIN
          `items_master_data_effects` ON `items_master_data_effects`.`item` = `items_master_data`.`item_id`
        WHERE
          `items_master_data`.`item_id` = :item
        GROUP BY
          `items_master_data`.`item_id`;
      ");
  
      $statement->bindParam(':item', $_POST['item'], PDO::PARAM_INT);

      $result = $statement->execute();
  
      if (!$result) {
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      $item = $statement->fetch();

      if (!$item) {
        responseError(404); // 存在しないアイテムだった場合は404(Not Found)を返して処理を中断
      }

      if (!$item['usable']) {
        responseError(403); // 使用できないアイテムだった場合は403(Forbidden)を返して処理を中断
      }

      // トランザクション開始
      $GAME_PDO->beginTransaction();

      // アイテムの所持数を更新
      $statement = $GAME_PDO->prepare("
        UPDATE
          `characters_items`
        SET
          `number` = `number` - 1
        WHERE
          `ENo`  = :ENo  AND
          `item` = :item;
      ");
  
      $statement->bindParam(':ENo',    $_SESSION['ENo'], PDO::PARAM_INT);
      $statement->bindParam(':item',   $_POST['item'],   PDO::PARAM_INT);
  
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

      // アイテム効果の発動
      $effects = parseItemsMasterDataEffects($item['effects']);
      $itemLogs = [];
      foreach ($effects as $effect) {
        $func = "item\\".$effect['effect'];
        $result = $func($_SESSION['ENo'], $effect['value']);
        
        if ($result === false) {
          $GAME_PDO->rollBack();
          responseError(500); // アイテム効果の発動に失敗した場合は500(Internal Server Error)を返して処理を中断
        } else {
          $itemLogs[] = $result; // アイテム効果を発動したらログをまとめる
        }
      }

      // ここまで全て成功した場合はコミット
      $GAME_PDO->commit();
    } else {
      // 上記のどれでもなければ400(Bad Request)を返して処理を中断
      responseError(400);
    }
  }


  $statement = $GAME_PDO->prepare("
    SELECT
      `items_master_data`.`item_id`,
      `items_master_data`.`name`,
      `items_master_data`.`description`,
      `items_master_data`.`usable`,
      `items_master_data`.`relinquishable`,
      `items_master_data`.`category`,
      `characters_items`.`number`
    FROM
      `characters_items`
    JOIN
      `items_master_data` ON `items_master_data`.`item_id` = `characters_items`.`item`
    WHERE
      `characters_items`.`ENo` = :ENo
    ORDER BY
      `items_master_data`.`item_id`;
  ");

  $statement->bindValue(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  $items = $statement->fetchAll();
  
  $PAGE_SETTING['TITLE'] = 'アイテム';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

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

.items th:nth-child(4), .items td:nth-child(4) {
  text-align: center;
  width: 60px;
}

.items th:nth-child(5), .items td:nth-child(5) {
  text-align: center;
  width: 70px;
}

.items th:nth-child(6), .items td:nth-child(6) {
  text-align: center;
  width: 60px;
}

.items input {
  box-sizing: border-box;
  height: 32px;
  width: 100%;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>アイテム</h1>

<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
<section>
  <h2>結果</h2>

  <p><?=implode('<br/>', $itemLogs)?></p>
</section>
<?php } ?>

<section>
  <h2>アイテム一覧</h2>

  <div id="error-message-area"></div>

  <table class="items">
    <thead>
      <tr>
        <th>アイテム名</th>
        <th>数</th>
        <th>アイテム説明</th>
        <th>使用</th>
        <th>破棄数</th>
        <th>破棄</th>
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
        <td>
          <?php if ($item['usable']) { ?>
            <form class="use-form" method="post" data-item="<?=$item['item_id']?>" data-max="<?=$item['number']?>">
              <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
              <input type="hidden" name="type" value="use">
              <input type="hidden" name="item" value="<?=$item['item_id']?>">

              <button class="use-button" data-item="<?=$item['item_id']?>">
                使用
              </button>
            </form>
          <?php } else { ?>
            -
          <?php } ?>
        </td>
        <td>
          <?php if ($item['relinquishable']) { ?>
            <input id="input-delete-item-number-<?=$item['item_id']?>" type="number" min="1" max="<?=$item['number']?>">
          <?php } else { ?>
            -
          <?php } ?>
        </td>
        <td>
          <?php if ($item['relinquishable']) { ?>
            <form class="relinquish-form" method="post" data-item="<?=$item['item_id']?>" data-max="<?=$item['number']?>">
              <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
              <input type="hidden" name="type" value="relinquish">
              <input type="hidden" name="item" value="<?=$item['item_id']?>">
              <input id="delete-item-number-<?=$item['item_id']?>" type="hidden" name="number">

              <button>
                破棄
              </button>
            </form>
          <?php } else { ?>
            -
          <?php } ?>
        </td>
      </tr>
<?php } ?>
    </tbody>
  </table>
</section>

<script>
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

  $('.use-form').submit(function() {
    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 送信
    waitingResponse = true; // レスポンス待ち状態をONに
  });

  $('.relinquish-form').submit(function() {
    // 対象のアイテムID及び破棄数上限を取得
    var item  = $(this).data().item;
    var max   = $(this).data().max;

    // 対象のアイテムIDの破棄数を取得
    var inputNumber = $('#input-delete-item-number-'+item).val();

    // 入力値検証
    // 破棄数が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputNumber || Number(inputNumber) == 0) {
      showErrorMessage('破棄数が入力されていません');
      return false;
    }

    // 破棄数の入力形式が不正な場合エラーメッセージを表示して処理を中断
    if (!/^[1-9][0-9]*$/.test(inputNumber)) {
      showErrorMessage('破棄数の入力形式が不正です');
      return false;
    }

    // 破棄数が所持数を超えている場合エラーメッセージを表示して処理を中断
    if (max < Number(inputNumber)) {
      showErrorMessage('破棄数が所持数を超えています');
      return false;
    }

    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 送信
    $('#delete-item-number-'+item).val(inputNumber);

    waitingResponse = true; // レスポンス待ち状態をONに
  });

</script>
<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>