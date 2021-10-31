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
      `notifications`.`notificated_at`
    FROM
      `notifications`
    WHERE
      (`notifications`.`type` = 'announcement' OR `notifications`.`type` = 'administrator') AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    UNION

    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      CONCAT('RNo.', `rooms`.`RNo`, ' ', `rooms`.`title`, 'にて', 'ENo.', `characters`.`ENo`, ' ', `characters`.`nickname`, 'からの返信があります。') AS `message`,
      `messages`.`message` AS `detail`,
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
      `notifications`.`type` = 'replied' AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    UNION

    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      CONCAT('RNo.', `rooms`.`RNo`, ' ', `rooms`.`title`, 'に', `notifications`.`count`, '件の新着があります。') AS `message`,
      '' AS `detail`,
      `notifications`.`notificated_at`
    FROM
      `notifications`
    JOIN
      `rooms` ON `notifications`.`target` = `rooms`.`RNo`
    WHERE
      `notifications`.`type` = 'new_arrival' AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    UNION

    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      CONCAT('ENo.', `characters`.`ENo`, ' ', `characters`.`nickname`, 'からお気に入りされました。') AS `message`,
      '' AS `detail`,
      `notifications`.`notificated_at`
    FROM
      `notifications`
    JOIN
      `characters` ON `notifications`.`target` = `characters`.`ENo`
    WHERE
      `notifications`.`type` = 'faved' AND
      (`notifications`.`ENo` IS NULL OR `notifications`.`ENo` = :ENo)

    UNION

    SELECT
      `notifications`.`id`,
      `notifications`.`type`,
      `notifications`.`target`,
      CONCAT('ENo.', `characters`.`ENo`, ' ', `characters`.`nickname`, 'からのダイレクトメッセージがあります。') AS `message`,
      `direct_messages`.`message` AS `detail`,
      `notifications`.`notificated_at`
    FROM
      `notifications`
    JOIN
      `direct_messages` ON `notifications`.`target` = `direct_messages`.`id`
    JOIN
      `characters`      ON `direct_messages`.`from` = `characters`.`ENo`
    WHERE
      `notifications`.`type` = 'direct_message' AND
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

  $PAGE_SETTING['TITLE'] = '通知';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

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
    <section class="notification">
      <div class="notification-message"><?=htmlspecialchars($notification['message'])?></div>
      <div class="notification-detail"><?=deleteProfileDecoration($notification['detail'])?></div>
      <div class="notification-timestamp"><?=$notification['notificated_at']?></div>
    </section>
<?php } ?>
  </section>
<?php } ?>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>