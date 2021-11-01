<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';
  
  require_once GETENV('GAME_ROOT').'/utils/decoration.php';
  
  $statement = $GAME_PDO->prepare("
    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      '管理者からのメッセージがあります。' AS `message`,
      `notifications`.`message` AS `detail`,
      null as `link_target`,
      `notifications`.`notificated_at`
    FROM
      `notifications`
    WHERE
      `notifications`.`type` = 'administrator' AND
      `notificated_at` < CURRENT_TIMESTAMP     AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    UNION

    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      'お知らせが更新されました。' AS `message`,
      `announcements`.`title` AS `detail`,
      null as `link_target`,
      `notifications`.`notificated_at`
    FROM
      `notifications`
    JOIN
      `announcements` ON `notifications`.`target` = `announcements`.`id`
    WHERE
      `notifications`.`type` = 'announcement' AND
      `notificated_at` < CURRENT_TIMESTAMP    AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    UNION

    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      (
        CASE
        WHEN `rooms`.`official` = true THEN
          CONCAT(`rooms`.`title`, 'にて', 'ENo.', `characters`.`ENo`, ' ', `characters`.`nickname`, 'からの返信があります。')
        ELSE
          CONCAT('RNo.', `rooms`.`RNo`, ' ', `rooms`.`title`, 'にて', 'ENo.', `characters`.`ENo`, ' ', `characters`.`nickname`, 'からの返信があります。')
        END
      )  AS `message`,
      `messages`.`message` AS `detail`,
      `rooms`.`RNo` as `link_target`,
      `notifications`.`notificated_at`
    FROM
      `notifications`
    JOIN
      `messages`   ON `notifications`.`target`  = `messages`.`id`
    JOIN
      `characters` ON `messages`.`ENo` = `characters`.`ENo`
    JOIN
      `rooms`      ON `messages`.`RNo` = `rooms`.`RNo`
    WHERE
      `notifications`.`type` = 'replied'   AND
      `notificated_at` < CURRENT_TIMESTAMP AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    UNION

    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      CONCAT('RNo.', `rooms`.`RNo`, ' ', `rooms`.`title`, 'に', `notifications`.`count`, '件の新着があります。') AS `message`,
      '' AS `detail`,
      `rooms`.`RNo` as `link_target`,
      `notifications`.`notificated_at`
    FROM
      `notifications`
    JOIN
      `rooms` ON `notifications`.`target` = `rooms`.`RNo`
    WHERE
      `notifications`.`type` = 'new_arrival' AND
      `notificated_at` < CURRENT_TIMESTAMP   AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    UNION

    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      CONCAT('ENo.', `characters`.`ENo`, ' ', `characters`.`nickname`, 'からお気に入りされました。') AS `message`,
      '' AS `detail`,
      `characters`.`ENo` as `link_target`,
      `notifications`.`notificated_at`
    FROM
      `notifications`
    JOIN
      `characters` ON `notifications`.`target` = `characters`.`ENo`
    WHERE
      `notifications`.`type` = 'faved'     AND
      `notificated_at` < CURRENT_TIMESTAMP AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    UNION

    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      CONCAT('ENo.', `characters`.`ENo`, ' ', `characters`.`nickname`, 'からのダイレクトメッセージがあります。') AS `message`,
      `direct_messages`.`message` AS `detail`,
      `characters`.`ENo` as `link_target`,
      `notifications`.`notificated_at`
    FROM
      `notifications`
    JOIN
      `direct_messages` ON `notifications`.`target` = `direct_messages`.`id`
    JOIN
      `characters`      ON `direct_messages`.`from` = `characters`.`ENo`
    WHERE
      `notifications`.`type` = 'direct_message' AND
      `notificated_at` < CURRENT_TIMESTAMP      AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    ORDER BY
      `notificated_at` DESC
    LIMIT
      :number;
  ");

  $statement->bindParam(':ENo',    $_SESSION['ENo']);
  $statement->bindParam(':number', $GAME_CONFIG['NOTIFICATIONS_LIMIT'], PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  $notifications = $statement->fetchAll();

  // 公式トークルームとして設定に登録されているRNoは置換する

  $PAGE_SETTING['TITLE'] = '通知';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>通知</h1>

<p>
  最新<?=$GAME_CONFIG['NOTIFICATIONS_LIMIT']?>件の通知を表示します。
</p>

<section>
  <h2>通知一覧</h2>

<?php if (!$notifications) { ?>
  <p>通知はありません。</p>
<?php } else { ?>
  <section class="notifications">
<?php foreach ($notifications as $notification) { ?>
<?php if (!is_null($notification['link_target']) || $notification['type'] == 'announcement') { ?>
  <?php if ($notification['type'] == 'announcement')   { ?><a href="<?=$GAME_CONFIG['URI']?>announcements" class="notification-link"><?php } ?>
  <?php if ($notification['type'] == 'replied')        { ?><a href="<?=$GAME_CONFIG['URI']?>room?room=<?=$notification['link_target']?>&mode=rel" class="notification-link"><?php } ?>
  <?php if ($notification['type'] == 'new_arrival')    { ?><a href="<?=$GAME_CONFIG['URI']?>room?room=<?=$notification['link_target']?>" class="notification-link"><?php } ?>
  <?php if ($notification['type'] == 'faved')          { ?><a href="<?=$GAME_CONFIG['URI']?>profile?ENo=<?=$notification['link_target']?>" class="notification-link"><?php } ?>
  <?php if ($notification['type'] == 'direct_message') { ?><a href="<?=$GAME_CONFIG['URI']?>messages/message?ENo=<?=$notification['link_target']?>" class="notification-link"><?php } ?>
<?php } ?>
    <section class="notification">
      <div class="notification-message"><?=htmlspecialchars($notification['message'])?></div>
      <div class="notification-detail"><?=deleteProfileDecoration($notification['detail'])?></div>
      <div class="notification-timestamp"><?=$notification['notificated_at']?></div>
    </section>
<?php if (!is_null($notification['link_target']) || $notification['type'] == 'announcement') { ?>
  </a>
<?php } ?>
<?php } ?>
  </section>
<?php } ?>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>