<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['type'])) { // 受け取ったデータにtypeがなければ400(Bad Request)を返して処理を中断
      responseError(400);
    }

    // typeごとに処理を振り分け
    if ($_POST['type'] == 'sell') {
      // 出品処理の場合
      // 入力値検証
      if (
        !validatePOST('sell_item',          ['natural-number']) ||
        !validatePOST('demand_item',        ['natural-number']) ||
        !validatePOST('sell_item_number',   ['non-empty', 'natural-number']) ||
        !validatePOST('demand_item_number', ['non-empty', 'natural-number'])
      ) {
        responseError(400);
      }

      if (strcmp($_POST['sell_item'], $_POST['demand_item']) === 0) {
        responseError(403); // 出品アイテムと希望アイテムが同じだった場合処理を中断
      }

      // 送信されたアイテムIDに設定されているものが空文字であればnullを、そうでなければ数値化したものをアイテムIDとする
      $sellItem   = $_POST['sell_item']   === '' ? null : intval($_POST['sell_item']);
      $demandItem = $_POST['demand_item'] === '' ? null : intval($_POST['demand_item']);

      // 出品条件が前提条件を満たしているかどうかを取得
      $statement = $GAME_PDO->prepare("
        SELECT
        	(
            EXISTS (
              SELECT
                *
              FROM
                `items_yield`
              WHERE
                `item` = :demand_item AND
                0 < `items_yield`.`yield`
            ) AND
            EXISTS (
              SELECT
                *
              FROM
                `characters_items`
              WHERE
                `ENo`  = :ENo       AND
                `item` = :sell_item AND
                :sell_item_number < `characters_items`.`number`
            ) AND
            EXISTS (
              SELECT
                *
              FROM
                `items_master_data`
              WHERE
                `item_id`  = :sell_item AND
                `tradable` = true
            ) AND
            EXISTS (
              SELECT
                *
              FROM
                `items_master_data`
              WHERE
                `item_id`  = :demand_item AND
                `tradable` = true
            )
			  );
      ");

      $statement->bindValue(':ENo',              $_SESSION['ENo']);
      $statement->bindValue(':sell_item',        $sellItem);
      $statement->bindValue(':demand_item',      $demandItem);
      $statement->bindValue(':sell_item_number', $_POST['sell_item_number'], PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      $condition = $statement->fetch();

      if (!$condition) {
        responseError(403); // 出品条件が前提条件を満たしていない場合は403(Forbidden)を返して処理を中断
      }

      // トランザクション開始
      $GAME_PDO->beginTransaction();

      // 出品情報を設定
      $statement = $GAME_PDO->prepare("
        INSERT INTO `flea_markets` (
          `seller`,
          `sell_item`,
          `sell_item_number`,
          `demand_item`,
          `demand_item_number`
        ) VALUES (
          :ENo,
          :sell_item,
          :sell_item_number,
          :demand_item,
          :demand_item_number
        );
      ");

      $statement->bindValue(':ENo',               $_SESSION['ENo']);
      $statement->bindValue(':sell_item',         $sellItem);
      $statement->bindValue(':demand_item',       $demandItem);
      $statement->bindValue(':sell_item_number',  $_POST['sell_item_number']);
      $statement->bindValue(':demand_item_number',$_POST['demand_item_number']);

      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      }

      if (is_null($sellItem)) {
        // 出品したアイテムがお金の場合
        // 所持金を減らす
        $statement = $GAME_PDO->prepare("
          UPDATE
            `characters`
          SET
            `money` = `money` - :sell_item_number
          WHERE
            `ENo` = :ENo;
        ");

        $statement->bindValue(':ENo',              $_SESSION['ENo']);
        $statement->bindValue(':sell_item_number', $_POST['sell_item_number']);

        $result = $statement->execute();

        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        }
      } else {
        // 出品したアイテムがお金でない場合
        // 所持数を減らす
        $statement = $GAME_PDO->prepare("
          UPDATE
            `characters_items`
          SET
            `number` = `number` - :sell_item_number
          WHERE
            `ENo`  = :ENo AND
            `item` = :sell_item;
        ");

        $statement->bindValue(':ENo',              $_SESSION['ENo']);
        $statement->bindValue(':sell_item',        $sellItem);
        $statement->bindValue(':sell_item_number', $_POST['sell_item_number']);

        $result = $statement->execute();

        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
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
      }

      // ここまで全て成功した場合はコミット
      $GAME_PDO->commit();
    } else if ($_POST['type'] == 'cancel') {
      // キャンセル処理の場合
      // 入力値検証
      if (!validatePOST('id', ['non-empty', 'natural-number'])) {
        responseError(400);
      }

      // 対象のidの出品を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `seller`,
          `sell_item`,
          `sell_item_number`,
          `state`
        FROM
          `flea_markets`
        WHERE
          `id` = :id;
      ");

      $statement->bindValue(':id',  $_POST['id'], PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      $target = $statement->fetch();

      if (!$target) {
        responseError(404); // 結果の取得に失敗した場合は404を返して処理を中断
      }

      if ($target['seller'] != $_SESSION['ENo']) {
        responseError(403); // 自分が出品したアイテムでない出品をキャンセルしようとしていた場合は403(Forbidden)を返して処理を中断
      }

      if ($target['state'] != 'sale') {
        responseError(400); // 販売中でないアイテムをキャンセルしようとしていた場合は400(Bad Request)を返して処理を中断
      }

      // トランザクション開始
      $GAME_PDO->beginTransaction();

      // 出品をキャンセル状態に
      $statement = $GAME_PDO->prepare("
        UPDATE
          `flea_markets`
        SET
          `state` = 'cancelled'
        WHERE
          `id` = :id;
      ");

      $statement->bindValue(':id', $_POST['id'], PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      }

      if (is_null($target['sell_item'])) {
        // 出品していたのがお金だった場合
        // お金の入手処理
        $statement = $GAME_PDO->prepare("
          UPDATE
            `characters`
          SET
            `money` = `money` + :number
          WHERE
            `ENo` = :ENo;
        ");

        $statement->bindParam(':ENo',    $_SESSION['ENo'],            PDO::PARAM_INT);
        $statement->bindParam(':number', $target['sell_item_number'], PDO::PARAM_INT);

        $result = $statement->execute();

        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
        }
      } else {
        // 出品していたのがアイテムだった場合
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
      
        $statement->bindParam(':ENo',    $_SESSION['ENo'],            PDO::PARAM_INT);
        $statement->bindParam(':item',   $target['sell_item'],        PDO::PARAM_INT);
        $statement->bindParam(':number', $target['sell_item_number'], PDO::PARAM_INT);
      
        $result = $statement->execute();
      
        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
        }
      }

      // ここまで全て成功した場合はコミット
      $GAME_PDO->commit();
    } else if ($_POST['type'] == 'buy') {
      // 購入処理の場合
      // 入力値検証
      if (!validatePOST('id', ['non-empty', 'natural-number'])) {
        responseError(400);
      }

      // 対象のidの出品を取得（出品者がブロック/被ブロックの関係である出品は検索対象としない）
      $statement = $GAME_PDO->prepare("
        SELECT
          `seller`,
          `sell_item`,
          `sell_item_number`,
          `demand_item`,
          `demand_item_number`,
          `state`
        FROM
          `flea_markets`
        WHERE
          `id` = :id AND
          NOT EXISTS (
            SELECT
              *
            FROM
              `characters_blocks`
            WHERE
              (`characters_blocks`.`blocker` = :ENo AND `characters_blocks`.`blocked` = `flea_markets`.`seller`) OR
              (`characters_blocks`.`blocked` = :ENo AND `characters_blocks`.`blocker` = `flea_markets`.`seller`)
          );
      ");

      $statement->bindParam(':ENo', $_SESSION['ENo'], PDO::PARAM_INT);
      $statement->bindParam(':id',  $_POST['id'],     PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }

      $target = $statement->fetch();

      if (!$target) {
        responseError(404); // 結果の取得に失敗した場合は404を返して処理を中断
      }

      if ($target['seller'] == $_SESSION['ENo']) {
        responseError(403); // 自分が出品したアイテムでない出品を購入しようとしていた場合は403(Forbidden)を返して処理を中断
      }

      if ($target['state'] != 'sale') {
        responseError(400); // 販売中でないアイテムを購入しようとしていた場合は400(Bad Request)を返して処理を中断
      }

      // トランザクション開始
      $GAME_PDO->beginTransaction();

      // 出品を購入済状態に
      $statement = $GAME_PDO->prepare("
        UPDATE
          `flea_markets`
        SET
          `state` = 'sold'
        WHERE
          `id` = :id;
      ");

      $statement->bindValue(':id', $_POST['id'], PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      }

      if (is_null($target['sell_item'])) {
        // 出品されていたのがお金だった場合
        // お金の入手処理
        $statement = $GAME_PDO->prepare("
          UPDATE
            `characters`
          SET
            `money` = `money` + :number
          WHERE
            `ENo` = :ENo;
        ");

        $statement->bindParam(':ENo',    $_SESSION['ENo'],            PDO::PARAM_INT);
        $statement->bindParam(':number', $target['sell_item_number'], PDO::PARAM_INT);

        $result = $statement->execute();

        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
        }
      } else {
        // 出品されていたのがアイテムだった場合
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
      
        $statement->bindParam(':ENo',    $_SESSION['ENo'],            PDO::PARAM_INT);
        $statement->bindParam(':item',   $target['sell_item'],        PDO::PARAM_INT);
        $statement->bindParam(':number', $target['sell_item_number'], PDO::PARAM_INT);
      
        $result = $statement->execute();
      
        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
        }
      }

      if (is_null($target['demand_item'])) {
        // 要求されたアイテムがお金の場合
        // 購入者の所持金を減らす
        $statement = $GAME_PDO->prepare("
          UPDATE
            `characters`
          SET
            `money` = `money` - :demand_item_number
          WHERE
            `ENo` = :ENo;
        ");

        $statement->bindValue(':ENo',                $_SESSION['ENo'],              PDO::PARAM_INT);
        $statement->bindValue(':demand_item_number', $target['demand_item_number'], PDO::PARAM_INT);

        $result = $statement->execute();

        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        }

        // 出品者の所持金を増やす
        $statement = $GAME_PDO->prepare("
          UPDATE
            `characters`
          SET
            `money` = `money` + :demand_item_number
          WHERE
            `ENo` = :seller;
        ");

        $statement->bindParam(':seller',             $target['seller'],             PDO::PARAM_INT);
        $statement->bindValue(':demand_item_number', $target['demand_item_number'], PDO::PARAM_INT);

        $result = $statement->execute();

        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
        }
      } else {
        // 要求されたアイテムがお金でない場合
        // 購入者のアイテム所持数を減らす
        $statement = $GAME_PDO->prepare("
          UPDATE
            `characters_items`
          SET
            `number` = `number` - :demand_item_number
          WHERE
            `ENo`  = :ENo AND
            `item` = :demand_item;
        ");

        $statement->bindValue(':ENo',                $_SESSION['ENo'],              PDO::PARAM_INT);
        $statement->bindValue(':demand_item',        $target['demand_item'],        PDO::PARAM_INT);
        $statement->bindValue(':demand_item_number', $target['demand_item_number'], PDO::PARAM_INT);

        $result = $statement->execute();

        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
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

        // 出品者のアイテム獲得処理
        $statement = $GAME_PDO->prepare("
          INSERT INTO `characters_items` (
            `ENo`,
            `item`,
            `number`
          ) VALUES (
            :seller,
            :demand_item,
            :demand_item_number
          );
        ");

        $statement->bindValue(':seller',             $target['seller'],             PDO::PARAM_INT);
        $statement->bindValue(':demand_item',        $target['demand_item'],        PDO::PARAM_INT);
        $statement->bindValue(':demand_item_number', $target['demand_item_number'], PDO::PARAM_INT);

        $result = $statement->execute();
      }

      // ここまで全て成功した場合はコミット
      $GAME_PDO->commit();
    } else {
      // 上記のどれでもなければ400(Bad Request)を返して処理を中断
      responseError(400);
    }
  }

  // 各URLパラメーターの初期値を設定
  $page   = isset($_GET['page'])   ? intval($_GET['page']) -1 : 0; // 現在のページ。pageの指定があればその値-1、指定がなければ0。インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $seller = isset($_GET['seller']) ? $_GET['seller'] : ''; // 出品者のENo。指定がなければ空文字列 
  $sell   = isset($_GET['sell'])   ? $_GET['sell']   : ''; // 出品アイテム名の一部。指定がなければ空文字列
  $demand = isset($_GET['demand']) ? $_GET['demand'] : ''; // 希望アイテム名の一部。指定がなければ空文字列

  // ページが負なら400(Bad Request)を返して処理を中断
  if ($page < 0) {
    responseError(400);
  }

  // 現在ページの出品されているアイテムを取得
  // 削除フラグが立っておらずページに応じた範囲の出品アイテムを検索
  // デフォルトの設定ではページ0であれば0件飛ばして30件、ページ1であれば30件飛ばして30件、ページ2であれば60件飛ばして30件、ページnであればn×30件飛ばして30件を表示します。
  // ただし、次のページがあるかどうか検出するために1件余分に取得します。
  // 31件取得してみて31件取得できれば次のページあり、取得結果の数がそれ未満であれば次のページなし（最終ページ）として扱います。
  // また、それぞれの検索条件によってSQL文を追記します。
  $statement = $GAME_PDO->prepare("
    SELECT
      `flea_markets`.`id`,
      `flea_markets`.`seller`    AS `seller_ENo`,
      `characters_icons`.`url`   AS `seller_icon`,
      IFNULL(`si`.`name`, 'お金') AS `sell_item_name`,
      IFNULL(`di`.`name`, 'お金') AS `demand_item_name`,
      `flea_markets`.`sell_item_number`,
      `flea_markets`.`demand_item_number`
    FROM
      `flea_markets`
    JOIN
      `characters` ON `characters`.`ENo` = `flea_markets`.`seller`
    LEFT JOIN
      `characters_icons` ON `characters_icons`.`ENo` = `flea_markets`.`seller`
    LEFT JOIN
      `items_master_data` AS `si` ON `si`.`item_id` = `flea_markets`.`sell_item`
    LEFT JOIN
      `items_master_data` AS `di` ON `di`.`item_id` = `flea_markets`.`demand_item`
    WHERE
      `flea_markets`.`state` = 'sale' AND
      `characters`.`deleted` = false  AND
      NOT EXISTS (
        SELECT
          *
        FROM
          `characters_blocks`
        WHERE
          (`characters_blocks`.`blocker` = :ENo AND `characters_blocks`.`blocked` = `flea_markets`.`seller`) OR
          (`characters_blocks`.`blocked` = :ENo AND `characters_blocks`.`blocker` = `flea_markets`.`seller`)
      ) ". 
      ($seller != "" ?  " AND `flea_markets`.`seller` = :seller " : "").
      ($sell   != "" ?  " AND ((`si`.`name` LIKE :sell)   OR (`si`.`name` IS NULL AND 'お金' LIKE :sell)) "   : "").
      ($demand != "" ?  " AND ((`di`.`name` LIKE :demand) OR (`di`.`name` IS NULL AND 'お金' LIKE :demand)) " : "").
      "
    ORDER BY
      `flea_markets`.`id` DESC
    LIMIT
      :offset, :number;
  ");

  $statement->bindValue(':ENo',    $_SESSION['ENo'],                                   PDO::PARAM_INT);
  $statement->bindValue(':offset', $page * $GAME_CONFIG['FLEA_MARKET_ITEMS_PER_PAGE'], PDO::PARAM_INT);
  $statement->bindValue(':number', $GAME_CONFIG['FLEA_MARKET_ITEMS_PER_PAGE'] + 1,     PDO::PARAM_INT);

  // 検索条件によってプレースホルダを追加
  if ($seller != "") $statement->bindValue(':seller', $seller);
  if ($sell   != "") $statement->bindValue(':sell',   '%'.$sell.'%');
  if ($demand != "") $statement->bindValue(':demand', '%'.$demand.'%');

  $result = $statement->execute();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  $marketItems = $statement->fetchAll();

  // 1件余分に取得できていれば次のページありとして余分な1件を切り捨て
  if (count($marketItems) == $GAME_CONFIG['FLEA_MARKET_ITEMS_PER_PAGE'] + 1) {
    $existsNext = true;
    array_pop($marketItems);
  } else {
  // 取得件数が足りなければ次のページなしとする
    $existsNext = false;
  }

  // 希望できるアイテムを取得（1個以上生成されており、トレード可能なアイテム）
  $statement = $GAME_PDO->prepare("
    SELECT
      `items_master_data`.`item_id`,
      `items_master_data`.`name`
    FROM
      `items_yield`
    JOIN
      `items_master_data` ON `items_master_data`.`item_id` = `items_yield`.`item`
    WHERE
      0 < `items_yield`.`yield` AND
      `items_master_data`.`tradable` = true;
  ");

  $statement->bindValue(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  $demandableItems = $statement->fetchAll();

  // キャラクターの所持金を取得
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

  // 所持しているアイテムを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `items_master_data`.`item_id`,
      `items_master_data`.`name`,
      `items_master_data`.`tradable`,
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

  // 所持しているアイテムのうち売ることができるアイテムを取得
  $tradableItems = [];
  foreach ($items as $item) {
    if ($item['tradable']) {
      $tradableItems[] = $item;
    }
  }

  $PAGE_SETTING['TITLE'] = 'フリーマーケット';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.market-items {
  border-collapse: collapse;
  margin: 0 auto;
  font-size: 15px;
  width: 100%;
}

.market-items th {
  background-color: #444;
  border: 1px solid #F8F8F8;
  color: #EEE;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  height: 30px;
}

.market-items td {
  border: 1px solid #F8F8F8;
  border-bottom: 1px solid lightgray;
  height: 55px;
}

.market-items th:nth-child(1), .market-items td:nth-child(1) {
  text-align: center;
  width: 60px;
}

.market-items th:nth-child(2), .market-items td:nth-child(2) {
  text-align: center;
}

.market-items td:nth-child(2) {
  color: #444;
  font-weight: bold;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
}

.market-items th:nth-child(3), .market-items td:nth-child(3) {
  text-align: center;
  width: 60px;
}

.market-items th:nth-child(4), .market-items td:nth-child(4) {
  text-align: center;
}

.market-items td:nth-child(4) {
  color: #444;
  font-weight: bold;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
}

.market-items th:nth-child(5), .market-items td:nth-child(5) {
  text-align: center;
  width: 60px;
}

.market-items th:nth-child(6), .market-items td:nth-child(6) {
  text-align: center;
  width: 60px;
}

.search-input {
  width: 80px;
  margin-right: 10px;
}
</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>フリーマーケット</h1>

<form method="get">
  <h2>検索条件</h2>
  
  <label>
    出品者ENo
    <input class="search-input" type="number" name="seller" value="<?=htmlspecialchars($seller)?>" min="1">
  </label>
    
  <label>
    出品アイテム
    <input class="search-input" type="text" name="sell" value="<?=htmlspecialchars($sell)?>">
  </label>
    
  <label>
    希望アイテム
    <input class="search-input" type="text" name="demand" value="<?=htmlspecialchars($demand)?>">
  </label>

  <button type="submit">検索</button>
</form>

<section>
  <h2>出品一覧</h2>

<?php if (!$marketItems) { ?>
  <p>出品されているアイテムがありません。</p>
<?php } else { ?>

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?seller=<?=htmlspecialchars($seller)?>&sell=<?=htmlspecialchars($sell)?>&demand=<?=htmlspecialchars($demand)?>&page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?seller=<?=htmlspecialchars($seller)?>&sell=<?=htmlspecialchars($sell)?>&demand=<?=htmlspecialchars($demand)?>&page=<?=$page+1+1?>">次のページ</a></div>
</section>

  <table class="market-items">
    <thead>
      <tr>
        <th>出品者</th>
        <th>出品アイテム</th>
        <th>出品数</th>
        <th>希望アイテム</th>
        <th>希望数</th>
        <th>交換</th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($marketItems as $item) { ?>
      <tr>
        <td>
          <a href="<?=$GAME_CONFIG['URI']?>profile?ENo=<?=$item['seller_ENo']?>" target="_blank">
            <?php
              $COMPONENT_ICON['src'] = $item['seller_icon'];
              include GETENV('GAME_ROOT').'/components/icon.php';
            ?>
          </a>
        </td>
        <td>
          <?=$item['sell_item_name']?>
        </td>
        <td>
          <?=$item['sell_item_number']?>
        </td>
        <td>
          <?=$item['demand_item_name']?>
        </td>
        <td>
          <?=$item['demand_item_number']?>
        </td>
        <td>
        <?php if ($item['seller_ENo'] == $_SESSION['ENo']) { ?>
          <form class=".decline-form" method="post">
            <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
            <input type="hidden" name="type" value="cancel">
            <input type="hidden" name="id" value="<?=$item['id']?>">

            <button>
              取下
            </button>
          </form>
        <?php } else { ?>
          <form class=".decline-form" method="post">
            <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
            <input type="hidden" name="type" value="buy">
            <input type="hidden" name="id" value="<?=$item['id']?>">

            <button>
              交換
            </button>
          </form>
        <?php } ?>
        </td>
      </tr>
<?php } ?>
    </tbody>
  </table>

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?seller=<?=htmlspecialchars($seller)?>&sell=<?=htmlspecialchars($sell)?>&demand=<?=htmlspecialchars($demand)?>&page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?seller=<?=htmlspecialchars($seller)?>&sell=<?=htmlspecialchars($sell)?>&demand=<?=htmlspecialchars($demand)?>&page=<?=$page+1+1?>">次のページ</a></div>
</section>

<?php } ?>
</section>

<form id="sell-form" method="post">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
  <input type="hidden" name="type" value="sell">

  <h2>出品</h2>

  <section class="form">
    <div class="form-title">出品アイテム</div>
    <select id="input-sell-item" class="form-input" name="sell_item">
      <option value="" data-max="<?=$character['money']?>">お金 (<?=$character['money']?>G所持)</option>
<?php foreach ($tradableItems as $item) { ?>
      <option value="<?=$item['item_id']?>" data-max="<?=$item['number']?>"><?=$item['name']?> (<?=$item['number']?>個所持)</option>
<?php } ?>
    </select>
  </section>

  <section class="form">
    <div class="form-title">出品数</div>
    <input id="input-sell-item-number" name="sell_item_number" class="form-input" type="number" placeholder="出品数" min="1">
  </section>

  <section class="form">
    <div class="form-title">希望アイテム</div>
    <select id="input-demand-item" class="form-input" name="demand_item">
      <option value="">お金</option>
<?php foreach ($demandableItems as $item) { ?>
      <option value="<?=$item['item_id']?>"><?=$item['name']?></option>
<?php } ?>
    </select>
  </section>

  <section class="form">
    <div class="form-title">希望数</div>
    <input id="input-demand-item-number" name="demand_item_number" class="form-input" type="number" placeholder="希望数" min="1">
  </section>

  <div id="sell-error-message-area"></div>

  <div class="button-wrapper">
    <button class="button">出品</button>
  </div>
</form>

<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）

  $('.use-form').submit(function() {
    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 送信
    waitingResponse = true; // レスポンス待ち状態をONに
  });

  // 出品時にエラーメッセージを表示する関数及びその関連処理
  var sellErrorMessageArea = $('#sell-error-message-area');
  function showSellErrorMessage(message) {
    sellErrorMessageArea.empty();
    sellErrorMessageArea.append(
      '<div class="message-banner message-banner-error">'+
        message +
      '</div>'
    );
  }

  $('#sell-form').submit(function() {
    // 入力欄の値を取得
    var inputSellItem         = $('#input-sell-item').val();
    var inputSellItemNumber   = $('#input-sell-item-number').val();
    var inputDemandItem       = $('#input-demand-item').val();
    var inputDemandItemNumber = $('#input-demand-item-number').val();

    // 対象のアイテムの出品数の最大を取得
    var inputSellItemNumberMax = $('#input-sell-item option:selected').data().max;
    
    // 入力値検証
    // 出品数が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputSellItemNumber || Number(inputSellItemNumber) == 0) {
      showSellErrorMessage('出品数が入力されていません');
      return false;
    }

    // 出品数の入力形式が不正な場合エラーメッセージを表示して処理を中断
    if (!/^[1-9][0-9]*$/.test(inputSellItemNumber)) {
      showSellErrorMessage('出品数の入力形式が不正です');
      return false;
    }

    // 出品数が所持数を超えている場合エラーメッセージを表示して処理を中断
    if (inputSellItemNumberMax < Number(inputSellItemNumber)) {
      showSellErrorMessage('出品数が所持数を超えています');
      return false;
    }

    // 希望数が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputDemandItemNumber || Number(inputDemandItemNumber) == 0) {
      showSellErrorMessage('希望数が入力されていません');
      return false;
    }

    // 希望数の入力形式が不正な場合エラーメッセージを表示して処理を中断
    if (!/^[1-9][0-9]*$/.test(inputDemandItemNumber)) {
      showSellErrorMessage('希望数の入力形式が不正です');
      return false;
    }

    // 出品アイテムと希望アイテムが同じ場合エラーメッセージを表示して処理を中断
    if (inputSellItem == inputDemandItem) {
      showSellErrorMessage('出品アイテムと希望アイテムに同じアイテムを設定することはできません');
      return false;
    }

    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 送信

    waitingResponse = true; // レスポンス待ち状態をONに
  });

</script>
<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>