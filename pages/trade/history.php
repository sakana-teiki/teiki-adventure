<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';
  
  // トレード履歴の取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `mc`.`ENo`                 AS `master_ENo`,
      `mc`.`nickname`            AS `master_nickname`,
      `tc`.`ENo`                 AS `target_ENo`,
      `tc`.`nickname`            AS `target_nickname`,
      `items_master_data`.`name` AS `item_name`,
      `trades`.`number`,
      `trades`.`updated_at`,
      `trades`.`state`,
      (
        CASE
          WHEN `trades`.`master` = :ENo THEN 'master'
          WHEN `trades`.`target` = :ENo THEN 'target'
          ELSE null 
        END
      ) AS `user_side`
    FROM
      `trades`
    JOIN
      `items_master_data` ON `items_master_data`.`item_id` = `trades`.`item`
    JOIN
      `characters` AS `mc` ON `mc`.`ENo` = `trades`.`master`
    JOIN
      `characters` AS `tc` ON `tc`.`ENo` = `trades`.`target`
    WHERE
      (`trades`.`master` = :ENo OR `trades`.`target` = :ENo) AND
      `mc`.`deleted` = false AND
      `tc`.`deleted` = false
    ORDER BY
      `updated_at` DESC
    LIMIT
      :number;
  ");

  $statement->bindParam(':ENo',    $_SESSION['ENo']);
  $statement->bindParam(':number', $GAME_CONFIG['TRADE_HISTORIES_LIMIT'], PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  $histories = $statement->fetchAll();

  $PAGE_SETTING['TITLE'] = 'トレード履歴';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>


.histories {
  border-bottom: 1px solid lightgray;
}

.history {
  box-sizing: border-box;
  padding: 5px 10px;
  border-top: 1px solid lightgray;
}

.history-link {
  text-decoration: none;
  user-select: none;
}

.history-message {
  color: #444;
  font-weight: bold;
}

.history-detail {
  width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #888;
}

.history-timestamp {
  text-align: right;
  font-size: 14px;
  color: #888;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>トレード履歴</h1>

<p>
  最新<?=$GAME_CONFIG['TRADE_HISTORIES_LIMIT']?>件のトレード履歴を表示します。
</p>

<section>
  <h2>トレード履歴一覧</h2>

<?php if (!$histories) { ?>
  <p>トレード履歴はありません。</p>
<?php } else { ?>
  <section class="histories">
<?php foreach ($histories as $history) { ?>
  <a href="<?=$GAME_CONFIG['URI']?>profile?ENo=<?=$history['user_side'] == 'master' ? $history['target_ENo'] : $history['master_ENo'] ?>" class="history-link">
    <section class="history">
      <div class="history-message">
        <?php if ($history['state'] == 'trading') { ?>
          ENo.<?=$history['master_ENo']?> <?=htmlspecialchars($history['master_nickname'])?>がENo.<?=$history['target_ENo']?> <?=htmlspecialchars($history['target_nickname'])?>に<?=$history['item_name']?> <?=$history['number']?>の送付を申請しました。
        <?php } else if ($history['state'] == 'finished') { ?>
          ENo.<?=$history['master_ENo']?> <?=htmlspecialchars($history['master_nickname'])?>がENo.<?=$history['target_ENo']?> <?=htmlspecialchars($history['target_nickname'])?>に送った<?=$history['item_name']?> <?=$history['number']?>個の送付申請は受領され、アイテムが送付されました。
        <?php } else if ($history['state'] == 'declined') { ?>
          ENo.<?=$history['master_ENo']?> <?=htmlspecialchars($history['master_nickname'])?>がENo.<?=$history['target_ENo']?> <?=htmlspecialchars($history['target_nickname'])?>に送った<?=$history['item_name']?> <?=$history['number']?>個の送付申請は辞退され、アイテムが返却されました。
        <?php } ?>
      </div>
      <div class="history-timestamp"><?=$history['updated_at']?></div>
    </section>
  </a>
<?php } ?>
  </section>
<?php } ?>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>