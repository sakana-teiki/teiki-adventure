<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/notification.php';
  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('eno',     ['non-empty', 'natural-number']) ||
      !validatePOST('message', ['disallow-special-chars'], $GAME_CONFIG['DIRECT_MESSEAGE_MAX_LENGTH'])
    ) {
      http_response_code(400);
      exit;
    }

    if ($_SESSION['ENo'] == $_POST['eno']) {
      // 自分自身にダイレクトメッセージを送信しようとしていた場合400(Bad Request)を返し処理を中断
      http_response_code(400);
      exit;
    }

    // 対象と対象との関係性の取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `characters`.`ENo`,
        `characters`.`nickname`,
        `characters`.`deleted`,
        `characters`.`webhook`,
        `characters`.`notification_direct_message`,
        `characters`.`notification_webhook_direct_message`,
        IFNULL((SELECT true FROM `characters_blocks` WHERE `blocker` = :user AND `blocked` = :target), false) AS `block`,
        IFNULL((SELECT true FROM `characters_blocks` WHERE `blocked` = :user AND `blocker` = :target), false) AS `blocked`
      FROM
        `characters`
      WHERE
        `ENo` = :target;
    ");

    $statement->bindParam(':user',   $_SESSION['ENo']);
    $statement->bindParam(':target', $_POST['eno']);
  
    $result = $statement->execute();
    $target = $statement->fetch();
  
    if (!$result || !$target) {
      // SQLの実行に失敗した場合あるいは結果が存在しない場合は500(Internal Server Error)を返し処理を中断
      http_response_code(500); 
      exit;
    }

    if ($target['deleted']) {
      // 対象が削除済の場合404(Not Found)を返し処理を中断
      http_response_code(404); 
      exit;
    }

    if ($target['block'] || $target['blocked']) {
      // ブロックしている、あるいはされている場合は403(Forbidden)を返し処理を中断
      http_response_code(403); 
      exit;
    }

    // メッセージの登録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `direct_messages` (
        `from`,
        `to`,
        `message`
      ) VALUES (
        :from,
        :to,
        :message
      );
    ");

    $statement->bindParam(':from',    $_SESSION['ENo']);
    $statement->bindParam(':to',      $_POST['eno']);
    $statement->bindParam(':message', $_POST['message']);

    $result = $statement->execute();

    if (!$result) {
      http_response_code(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
      exit;
    }

    $lastInsertId = intval($GAME_PDO->lastInsertId()); // idを取得

    if ($target['notification_direct_message']) {
      // 対象のダイレクトメッセージ通知が有効なら通知を作成
      $statement = $GAME_PDO->prepare("
        INSERT INTO `notifications` (
          `ENo`,
          `type`,
          `target`,
          `message`
        ) VALUES (
          :ENo,
          'direct_message',
          :target,
          ''
        );
      ");

      $statement->bindParam(':ENo',    $_POST['eno']);
      $statement->bindParam(':target', $lastInsertId);

      $result = $statement->execute();
    }

    if ($target['webhook'] && $target['notification_webhook_direct_message']) {
      // 対象のWebhookが入力されており、Discordのダイレクトメッセージ通知が有効なら通知を送信
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

      notifyDiscord($target['webhook'], 'ENo.'.$_SESSION['ENo'].' '.$nickname['nickname'].'からのダイレクトメッセージがあります。');
    }
  }

  // メッセージの取得
  $statement = $GAME_PDO->prepare("
    SELECT
      *
    FROM (
      (
        SELECT
          `direct_messages`.`message`,
          `direct_messages`.`sended_at`,
          `direct_messages`.`from` AS `user_ENo`,
          `direct_messages`.`to`   AS `target_ENo`,
          `cf`.`nickname`          AS `user_nickname`,
          `ct`.`nickname`          AS `target_nickname`,
          'sended'                 AS `type`
        FROM
          `direct_messages`
        JOIN
          `characters` AS `cf` ON `cf`.`ENo` = `direct_messages`.`from`
        JOIN
          `characters` AS `ct` ON `ct`.`ENo` = `direct_messages`.`to`
        WHERE
          `direct_messages`.`from` = :ENo AND
          `cf`.`deleted` = false          AND
          `ct`.`deleted` = false          AND
          NOT EXISTS (
            SELECT
              *
            FROM
              `characters_blocks`
            WHERE
              (`characters_blocks`.`blocker` = `direct_messages`.`from` AND `characters_blocks`.`blocked` = `direct_messages`.`to`  ) OR
              (`characters_blocks`.`blocker` = `direct_messages`.`to`   AND `characters_blocks`.`blocked` = `direct_messages`.`from`)
          )
        ORDER BY
          `direct_messages`.`sended_at` DESC
      )

      UNION ALL
    
      (
        SELECT
          `direct_messages`.`message`,
          `direct_messages`.`sended_at`,
          `direct_messages`.`to`   AS `user_ENo`,
          `direct_messages`.`from` AS `target_ENo`,
          `ct`.`nickname`          AS `user_nickname`,
          `cf`.`nickname`          AS `target_nickname`,
          'recieved'               AS `type`
        FROM
          `direct_messages`
        JOIN
          `characters` AS `cf` ON `cf`.`ENo` = `direct_messages`.`from`
        JOIN
          `characters` AS `ct` ON `ct`.`ENo` = `direct_messages`.`to`
        WHERE
          `direct_messages`.`to` = :ENo AND
          `cf`.`deleted` = false        AND
          `ct`.`deleted` = false        AND
          NOT EXISTS (
            SELECT
              *
            FROM
              `characters_blocks`
            WHERE
              (`characters_blocks`.`blocker` = `direct_messages`.`from` AND `characters_blocks`.`blocked` = `direct_messages`.`to`  ) OR
              (`characters_blocks`.`blocker` = `direct_messages`.`to`   AND `characters_blocks`.`blocked` = `direct_messages`.`from`)
          )
        ORDER BY
          `direct_messages`.`sended_at` DESC
      )
    ) AS `ungrouped_direct_messages`
    GROUP BY
      `ungrouped_direct_messages`.`user_ENo`, `ungrouped_direct_messages`.`target_ENo`
    ORDER BY
      `ungrouped_direct_messages`.`sended_at` DESC;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo'], PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    http_response_code(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    exit;
  }

  $messages = $statement->fetchAll();

  $PAGE_SETTING['TITLE'] = 'ダイレクトメッセージ';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.direct-messages {
  width: 95%;
  margin: 0 auto;
  border-bottom: 1px solid lightgray;
}

.direct-message {
  box-sizing: border-box;
  padding: 5px 10px;
  border-top: 1px solid lightgray;
}

.direct-message-details {
  display: flex;
  justify-content: space-between;
}

.direct-message-link {
  text-decoration: none;
  user-select: none;
}

.direct-message-nickname {
  font-size: 16px;
  font-weight: bold;
  color: #222222;
}

.direct-message-eno {
  font-size: 13px;
  margin-left: 3px;
  color: gray;
}

.direct-message-timestamp {
  color: #888;
  font-size: 14px;
}

.direct-message-body {
  
}

.direct-message-body-ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #888;
}

.direct-message-body-nickname {
  color: #444;
  font-weight: bold;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>ダイレクトメッセージ</h1>

<section>
  <h2>ダイレクトメッセージ一覧</h2>

<?php if (!$messages) { ?>
  <p>ダイレクトメッセージはありません。</p>
<?php } else { ?>
  <section class="direct-messages">
<?php foreach ($messages as $message) { ?>
    <section class="direct-message">
      <a class="direct-message-link" href="<?=$GAME_CONFIG['URI']?>messages/message?ENo=<?=$message['target_ENo']?>">
        <div class="direct-message-details">
          <div>
            <span class="direct-message-nickname"><?=htmlspecialchars($message['target_nickname'])?></span>
            <span class="direct-message-eno">&lt; ENo.<?=$message['target_ENo']?> &gt;</span>
          </div>
          <div class="direct-message-timestamp"><?=$message['sended_at']?></div>
        </div>
        <div class="direct-message-body-ellipsis">
          <span class="direct-message-body-nickname"><?=$message['type'] == 'recieved' ? htmlspecialchars($message['target_nickname']) : htmlspecialchars($message['user_nickname']) ?>:</span>
          <?=htmlspecialchars($message['message'])?>
        </div>
      </a>
    </section>
<?php }?>
  </section>
<?php } ?>

</section>

<section>
  <h2>新規ダイレクトメッセージ</h2>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    
    <section class="form">
      <div class="form-title">送信先ENo</div>
      <input id="input-eno" class="form-input" type="number" name="eno" placeholder="ENo">
      <button id="check-eno" type="button" class="form-check-button">送信先を確認</button>
    </section>

    <section class="form">
      <div class="form-title">メッセージ（<?=$GAME_CONFIG['DIRECT_MESSEAGE_MAX_LENGTH']?>文字まで）</div>
      <textarea id="input-message" class="form-textarea" type="text" name="message" placeholder="メッセージ"></textarea>
    </section>

    <div id="error-message-area"></div>

    <div class="button-wrapper">
      <button class="button" type="submit">送信</button>
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
    var inputENo     = $('#input-eno').val();
    var inputMessage = $('#input-message').val();

    // 入力値検証
    // ENoが入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputENo) {
      showErrorMessage('ENoが入力されていません');
      return false;
    }

    // ENoの入力内容が不正な場合エラーメッセージを表示して送信を中断
    if (Number(inputENo) <= 0) {
      showErrorMessage('入力されたENoの値が不正です');
      return false;
    }

    // 送信先ENoが自分自身の場合エラーメッセージを表示して送信を中断
    if (Number(inputENo) == <?=$_SESSION['ENo'] ?>) {
      showErrorMessage('自分自身にダイレクトメッセージを送ることはできません');
      return false;
    }
    
    // メッセージが長すぎる場合エラーメッセージを表示して送信を中断
    if (inputMessage.length > <?=$GAME_CONFIG['DIRECT_MESSEAGE_MAX_LENGTH']?>) {
      showErrorMessage('メッセージが長すぎます');
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

<?php require GETENV('GAME_ROOT').'/components/footer.php'; // ページ下部の読み込み ?>