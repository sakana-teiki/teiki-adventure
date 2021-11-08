<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/notification.php';
  require_once GETENV('GAME_ROOT').'/utils/validation.php';
  require_once GETENV('GAME_ROOT').'/utils/decoration.php';

  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $targetENo = $_POST['eno']; // POSTの場合POSTのenoを対象とする
  } else {
    $targetENo = $_GET['ENo'];  // GETの場合URLパラメータのENoを対象とする
  }

  // 現在のページ
  // pageの指定があればその値-1、指定がなければ0
  // インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $page = isset($_GET['page']) ? intval($_GET['page']) -1 : 0;

  // ページが負なら400(Bad Request)を返して処理を中断
  if ($page < 0) {
    responseError(400); 
  }  

  if ($_SESSION['ENo'] == $targetENo) {
    // 自分自身が対象の場合400(Bad Request)を返し処理を中断
    responseError(400);
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
  $statement->bindParam(':target', $targetENo);
  
  $result = $statement->execute();
  $target = $statement->fetch();
  
  if (!$result || !$target) {
    // SQLの実行に失敗した場合あるいは結果が存在しない場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  if ($target['deleted']) {
    // 対象が削除済の場合404(Not Found)を返し処理を中断
    responseError(404);
  }

  if ($target['block'] || $target['blocked']) {
    // ブロックしている、あるいはされている場合は403(Forbidden)を返し処理を中断
    responseError(403);
  }

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('eno',     ['non-empty', 'natural-number']) ||
      !validatePOST('message', ['disallow-special-chars'], $GAME_CONFIG['DIRECT_MESSEAGE_MAX_LENGTH'])
    ) {
      responseError(400);
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
      responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
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

      notifyDiscord($target['webhook'], 'ENo.'.$_SESSION['ENo'].' '.$nickname['nickname'].'からのダイレクトメッセージがあります。 '.$GAME_CONFIG['ABSOLUTE_URI'].'messages/message?ENo='.$_SESSION['ENo']);
    }
  }

  // メッセージの取得
  // デフォルトの設定ではページ0であれば0件飛ばして50件、ページ1であれば50件飛ばして50件、ページ2であれば100件飛ばして50件、ページnであればn×50件飛ばして50件を表示します。
  // ただし、次のページがあるかどうか検出するために1件余分に取得します。
  // 51件取得してみて51件取得できれば次のページあり、取得結果の数がそれ未満であれば次のページなし（最終ページ）として扱います。
  $statement = $GAME_PDO->prepare("
    SELECT
      `direct_messages`.`message`,
      `direct_messages`.`sended_at`,
      `direct_messages`.`from` AS `from_ENo`,
      `direct_messages`.`to`   AS `target_ENo`,
      `cf`.`nickname`          AS `from_nickname`,
      `ct`.`nickname`          AS `target_nickname`
    FROM
      `direct_messages`
    JOIN
      `characters` AS `cf` ON `cf`.`ENo` = `direct_messages`.`from`
    JOIN
      `characters` AS `ct` ON `ct`.`ENo` = `direct_messages`.`to`
    WHERE
      (
        (`direct_messages`.`from` = :ENo    AND `direct_messages`.`to` = :target) OR
        (`direct_messages`.`from` = :target AND `direct_messages`.`to` = :ENo)
      ) AND
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
    LIMIT
      :offset, :number;
  ");

  $statement->bindParam(':ENo',    $_SESSION['ENo'], PDO::PARAM_INT);
  $statement->bindParam(':target', $target['ENo'],   PDO::PARAM_INT);
  $statement->bindValue(':offset', $page * $GAME_CONFIG['DIRECT_MESSAGES_PER_PAGE'], PDO::PARAM_INT);
  $statement->bindValue(':number', $GAME_CONFIG['DIRECT_MESSAGES_PER_PAGE'] + 1,     PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
  }

  $messages = $statement->fetchAll();

  // 1件余分に取得できていれば次のページありとして余分な1件を切り捨て
  if (count($messages) == $GAME_CONFIG['DIRECT_MESSAGES_PER_PAGE'] + 1) {
    $existsNext = true;
    array_pop($messages);
  } else {
  // 取得件数が足りなければ次のページなしとする
    $existsNext = false;
  }

  $PAGE_SETTING['TITLE'] = 'ダイレクトメッセージ | ENo.'.$target['ENo'].' '.$target['nickname'];

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

<h1><?=htmlspecialchars('ダイレクトメッセージ | ENo.'.$target['ENo'].' '.$target['nickname'])?></h1>

<section>
  <h2>メッセージ一覧</h2>

<?php if (!$messages) { ?>
  <p>ダイレクトメッセージはありません。</p>
<?php } else { ?>

  <section class="pagelinks next-prev-pagelinks">
    <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?ENo=<?=$targetENo?>&page=<?=$page+1-1?>">前のページ</a></div>
    <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
    <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?ENo=<?=$targetENo?>&page=<?=$page+1+1?>">次のページ</a></div>
  </section>

  <section class="direct-messages">
<?php foreach ($messages as $message) { ?>
    <section class="direct-message">
      <div class="direct-message-details">
        <div>
          <span class="direct-message-nickname"><?=htmlspecialchars($message['from_nickname'])?></span>
          <span class="direct-message-eno">&lt; ENo.<?=$message['from_ENo']?> &gt;</span>
        </div>
        <div class="direct-message-timestamp"><?=$message['sended_at']?></div>
      </div>
      <div class="direct-message-body">
        <?=newLineDecoration($message['message'])?>
      </div>
    </section>
<?php }?>
  </section>

  <section class="pagelinks next-prev-pagelinks">
    <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?ENo=<?=$targetENo?>&page=<?=$page+1-1?>">前のページ</a></div>
    <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
    <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?ENo=<?=$targetENo?>&page=<?=$page+1+1?>">次のページ</a></div>
  </section>

<?php } ?>

</section>

<section>
  <h2>新規ダイレクトメッセージ</h2>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input type="hidden" name="eno" value="<?=$target['ENo']?>">

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
    var inputMessage = $('#input-message').val();

    // 入力値検証    
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