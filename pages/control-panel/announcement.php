<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('title',   ['non-empty']) ||
      !validatePOST('message', ['non-empty']) ||
      !validatePOST('time',    [])
    ) {
      responseError(400);
    }

    // お知らせの登録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `announcements` (
        `title`,
        `message`,
        `announced_at`
      ) VALUES (
        :title,
        :message,
        :announcedAt
      );
    ");

    $statement->bindParam(':title',       $_POST['title']);
    $statement->bindParam(':message',     $_POST['message']);
    $statement->bindValue(':announcedAt', $_POST['time'] ? $_POST['time'] : date('Y-m-d H:i:s'), PDO::PARAM_STR);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    $lastInsertId = intval($GAME_PDO->lastInsertId()); // idを取得

    // 通知の発行
    $statement = $GAME_PDO->prepare("
      INSERT INTO `notifications` (
        `ENo`,
        `type`,
        `target`,
        `message`,
        `notificated_at`
      ) VALUES (
        null,
        'announcement',
        :id,
        '',
        :notificated_at
      );
    ");

    $statement->bindParam(':id',             $lastInsertId);
    $statement->bindValue(':notificated_at', $_POST['time'] ? $_POST['time'] : date('Y-m-d H:i:s'), PDO::PARAM_STR);

    $result = $statement->execute();

    // リダイレクト
    header('Location:'.$GAME_CONFIG['URI'].'announcements', true, 302);
    exit;
  }

  $PAGE_SETTING['TITLE'] = 'お知らせ発行';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>お知らせ発行</h1>

<section>
  <h2>お知らせ内容</h2>

  <form id="announce-form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

    <section class="form">
      <div class="form-title">タイトル</div>
      <input id="input-title" class="form-input" type="text" name="title" placeholder="タイトル">
    </section>

    <section class="form">
      <div class="form-title">お知らせ内容</div>
      <div class="form-description">
        お知らせ内容はHTML文として解釈されて実行されます。
      </div>
      <textarea id="input-message" class="form-textarea" type="text" name="message" placeholder="お知らせ内容"></textarea>
    </section>

    <section class="form">
      <div class="form-title">お知らせ日時</div>
      <div class="form-description">
        お知らせの日時を指定します。未来の日時を指定した場合、その日時以降に表示されます。<br>
        未入力の場合は現在日時が設定されます。
      </div>
      <input id="input-time" class="form-input" type="datetime-local" name="time">
    </section>

    <div id="error-message-area"></div>

    <div class="button-wrapper">
      <button class="button" type="submit">作成</button>
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

  $('#announce-form').submit(function(){
    // 値を取得
    var inputTitle   = $('#input-title').val();
    var inputMessage = $('#input-message').val();

    // 入力値検証
    // タイトルが入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputTitle) {
      showErrorMessage('タイトルが入力されていません');
      return false;
    }

    // お知らせ内容が入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputMessage) {
      showErrorMessage('お知らせ内容が入力されていません');
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