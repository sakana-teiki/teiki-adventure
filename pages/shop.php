<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('item',   ['non-empty', 'non-negative-integer']) ||
      !validatePOST('number', ['non-empty', 'natural-number'])
    ) {
      responseError(400);
    }

    // アイテムの情報を取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `item_id`,
        `name`,
        `price`,
        `shop`,
        `creatable`
      FROM
        `items_master_data`
      WHERE
        `item_id`   = :item;
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

    if (!$item['shop'] || !$item['creatable']) {
      responseError(403); // 販売していないアイテムだった場合は403(Forbidden)を返して処理を中断
    }

    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // 所持金を更新
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `money` = `money` - :price
      WHERE
        `ENo` = :ENo;
    ");
  
    $statement->bindParam(':ENo',   $_SESSION['ENo'],                          PDO::PARAM_INT);
    $statement->bindValue(':price', $item['price'] * intval($_POST['number']), PDO::PARAM_INT);
  
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
    $statement->bindParam(':item',   $_POST['item'],   PDO::PARAM_INT);
    $statement->bindParam(':number', $_POST['number'], PDO::PARAM_INT);
  
    $result = $statement->execute();
  
    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // アイテムの排出量の更新
    $statement = $GAME_PDO->prepare("
      INSERT INTO `items_yield` (
        `item`,
        `yield`
      ) VALUES (
        :item,
        :number
      )

      ON DUPLICATE KEY UPDATE
        `yield` = `yield` + :number
    ");
  
    $statement->bindParam(':item',   $_POST['item'],   PDO::PARAM_INT);
    $statement->bindParam(':number', $_POST['number'], PDO::PARAM_INT);
  
    $result = $statement->execute();
  
    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // ここまで全て成功した場合はコミット
    $GAME_PDO->commit();
  }

  // 所持金を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `money`
    FROM
      `characters`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindValue(':ENo', $_SESSION['ENo']);

  $result    = $statement->execute();
  $character = $statement->fetch();

  if (!$result || !$character) {
    responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  // 販売アイテム情報を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `items_master_data`.`item_id`,
      `items_master_data`.`name`,
      `items_master_data`.`price`,
      `items_master_data`.`description`,
      `items_master_data`.`category`,
      IFNULL((SELECT `characters_items`.`number` FROM `characters_items` WHERE `characters_items`.`item` = `items_master_data`.`item_id` AND `ENo` = :ENo), 0) AS `number`
    FROM
      `items_master_data`
    WHERE
      `items_master_data`.`shop`      = true AND
      `items_master_data`.`creatable` = true
    ORDER BY
      `items_master_data`.`item_id`;
  ");

  $statement->bindValue(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  $items = $statement->fetchAll();
  
  $PAGE_SETTING['TITLE'] = 'アイテムショップ';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.money {
  padding: 20px 20px 0 20px;
  font-weight: bold;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  font-size: 24px;
  color: #666;
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
  width: 60px;
}

.items th:nth-child(4), .items td:nth-child(4) {
  text-align: center;
  width: 40px;
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

<h1>アイテムショップ</h1>

<div class="money">
  所持金:<?=$character['money']?>G
</div>

<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
<section>
  <h2>購入結果</h2>

  <p><?=$item['name']?>を<?=$_POST['number']?>個購入しました。</p>
</section>
<?php } ?>

<section>
  <h2>商品一覧</h2>

  <div id="error-message-area"></div>

  <table class="items">
    <thead>
      <tr>
        <th>アイテム名</th>
        <th>価格</th>
        <th>アイテム説明</th>
        <th>所持</th>
        <th>購入数</th>
        <th>購入</th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($items as $item) { ?>
      <tr>
        <td>
          <?= $item['name'] ?>
        </td>
        <td>
          <?= $item['price'] ?>
        </td>
        <td>
          <?= $item['description'] ?>
        </td>
        <td>
          <?= $item['number'] ?>
        </td>
        <td>
          <input id="input-item-number-<?=$item['item_id']?>" type="number" min="<?=$item['price'] <= $character['money'] ? 1 : 0?>" max="<?=floor($character['money']/$item['price'])?>">
        </td>
        <td>
          <form class="buy-form" method="post" data-item="<?=$item['item_id']?>" data-max="<?=floor($character['money']/$item['price'])?>">
            <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
            <input type="hidden" name="item" value="<?=$item['item_id']?>">
            <input id="item-number-<?=$item['item_id']?>" type="hidden" name="number">

            <button>
              購入
            </button>
          </form>
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

  $('.buy-form').submit(function() {
    // 対象のアイテムID及び購入数上限を取得
    var item  = $(this).data().item;
    var max   = $(this).data().max;

    // 対象のアイテムIDの購入数を取得
    var inputNumber = $('#input-item-number-'+item).val();

    // 入力値検証
    // 購入数が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputNumber || Number(inputNumber) == 0) {
      showErrorMessage('購入数が入力されていません');
      return false;
    }

    // 購入数の入力形式が不正な場合エラーメッセージを表示して処理を中断
    if (!/^[1-9][0-9]*$/.test(inputNumber)) {
      showErrorMessage('購入数の入力形式が不正です');
      return false;
    }

    // 購入数が上限を超えている場合エラーメッセージを表示して処理を中断
    if (max < Number(inputNumber)) {
      showErrorMessage('所持金が足りません');
      return false;
    }

    // 送信
    $('#item-number-'+item).val(inputNumber);

    waitingResponse = true; // レスポンス待ち状態をONに
  });

</script>
<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>