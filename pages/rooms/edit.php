<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POST/GETで$RNoを取得する
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // POSTの場合
    if (!validatePOST('room', ['non-empty', 'integer'])) {
      http_response_code(400); 
      exit;
    }

    $RNo = $_POST['room']; // POSTのRNoの値を取得
  } else {
    // GETの場合
    if (!validateGET('room', ['non-empty', 'integer'])) {
      http_response_code(400); 
      exit;
    }

    $RNo = $_GET['room']; // GETのRNoの値を取得
  }

  // DBからトークルームのデータを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `RNo`,
      `administrator`,
      `title`,
      `summary`,
      `description`,
      (SELECT GROUP_CONCAT(`tag` SEPARATOR ' ') FROM `rooms_tags` WHERE `RNo` = :RNo GROUP BY `RNo`) AS `tags`
    FROM
      `rooms`
    WHERE
      `RNo`     = :RNo AND
      `deleted` = false;
  ");

  $statement->bindParam(':RNo', $RNo);

  $result = $statement->execute();
  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  $room = $statement->fetch();

  if (!$room) {
    // トークルームの取得に失敗した場合は404(Not Found)を返し処理を中断
    http_response_code(404); 
    exit;
  }

  if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR && $room['administrator'] != $_SESSION['ENo']) {
    // ゲームの管理者でもトークルームの管理者でもない場合は403(Forbidden)を返し処理を中断
    http_response_code(403); 
    exit;
  }

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('title',        ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['ROOM_TITLE_MAX_LENGTH'])       ||
      !validatePOST('description',  [                            'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['ROOM_DESCRIPTION_MAX_LENGTH']) ||
      !validatePOST('summary',      [             'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH'])     ||
      !validatePOST('tags',         [             'single-line', 'disallow-special-chars']) ||
      !validatePOST('delete_check', [])
    ) {
      http_response_code(400);
      exit;
    }

    // 部屋の削除を行う場合
    if ($_POST['delete_check'] == 'DELETE') {
      // トークルームの削除フラグをONに
      $statement = $GAME_PDO->prepare("
        UPDATE
          `rooms`
        SET
          `deleted` = true
        WHERE
          `RNo` = :RNo;
      ");

      $statement->bindParam(':RNo', $RNo);

      $result = $statement->execute();
      
      if (!$result) {
        http_response_code(500); // 失敗した場合は500(Internal Server Error)を返して処理を中断
        exit;
      } else {
        header('Location:'.$GAME_CONFIG['URI'].'rooms', true, 302); // 成功した場合はトークルーム一覧にリダイレクト
        exit;
      }
    }

    // DB登録処理
    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // トークルームの更新
    $statement = $GAME_PDO->prepare("
      UPDATE
        `rooms`
      SET
        `title`       = :title,
        `summary`     = :summary,
        `description` = :description
      WHERE
        `RNo` = :RNo;
    ");

    $statement->bindParam(':RNo',         $RNo);
    $statement->bindParam(':title',       $_POST['title']);
    $statement->bindParam(':summary',     $_POST['summary']);
    $statement->bindParam(':description', $_POST['description']);

    $result = $statement->execute();

    if (!$result) {
      http_response_code(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
      $GAME_PDO->rollBack();
      exit;
    }

    $room['title']       = $_POST['title'];
    $room['summary']     = $_POST['summary'];
    $room['description'] = $_POST['description'];

    // すでに登録されているタグの削除
    $statement = $GAME_PDO->prepare("
      DELETE FROM
        `rooms_tags`
      WHERE
        `RNo` = :RNo;
    ");

    $statement->bindParam(':RNo', $RNo);

    $result = $statement->execute();
    
    if (!$result) {
      http_response_code(500);
      $GAME_PDO->rollBack();
      exit;
    }

    // タグの登録
    $tags = explode(' ', $_POST['tags']);
    $tags = array_filter($tags, "strlen"); // 空行の要素は削除する

    foreach ($tags as $tag) {
      $statement = $GAME_PDO->prepare("
        INSERT INTO `rooms_tags` (
          `RNo`, `tag`
        ) VALUES (
          :RNo, :tag
        );
      ");

      $statement->bindParam(':RNo', $RNo);
      $statement->bindParam(':tag', $tag);

      $result = $statement->execute();
      
      if (!$result) {
        http_response_code(500);
        $GAME_PDO->rollBack();
        exit;
      }
    }

    $room['tags'] = implode(' ', $tags);

    // ここまで全て成功した場合はコミット
    $GAME_PDO->commit();
  }

  $PAGE_SETTING['TITLE'] = 'トークルーム編集';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<form id="update-room-form" method="post">
  <input type="hidden" name="room" value="<?=htmlspecialchars($RNo)?>">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

  <h2>トークルーム編集</h2>

  <section class="form">
    <div class="form-title">タイトル（<?=$GAME_CONFIG['ROOM_TITLE_MAX_LENGTH']?>文字まで）</div>
    <input id="input-title" class="form-input" type="text" name="title" placeholder="タイトル" value="<?=htmlspecialchars($room['title'])?>">
  </section>

  <section class="form">
    <div class="form-title">タグ（<?=$GAME_CONFIG['ROOM_TAG_MAX']?>個、各タグ<?=$GAME_CONFIG['ROOM_TAG_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      半角スペースで区切ることで複数指定できます。
    </div>
    <input id="input-tags" class="form-input-long" type="text" name="tags" placeholder="タグ" value="<?=htmlspecialchars($room['tags'])?>">
  </section>

  <section class="form">
    <div class="form-title">サマリー（<?=$GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      トークルーム一覧で表示される短い文章です。
    </div>
    <input id="input-summary" class="form-input-long" type="text" name="summary" placeholder="サマリー" value="<?=htmlspecialchars($room['summary'])?>">
  </section>

  <section class="form">
    <div class="form-title">説明文（<?=$GAME_CONFIG['ROOM_DESCRIPTION_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      説明文はプロフィールと同様の書式で装飾することができます。<br>
      詳しくはルールブックを確認してください。
    </div>
    <textarea id="input-description" class="form-textarea" type="text" name="description" placeholder="説明文"><?=htmlspecialchars($room['description'])?></textarea>
  </section>
  
  <h2>トークルーム削除</h2>
  <section class="form">
    <div class="form-description">トークルーム削除を行う場合は、以下の入力欄に「DELETE」と入力してください。</div>
    <input id="input-delete-check" class="form-input" type="text" name="delete_check">
  </section>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button class="button" type="submit">更新</button>
  </div>
</form>

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

  $('#update-room-form').submit(function(){
    // 値を取得
    var inputTitle       = $('#input-title').val();
    var inputSummary     = $('#input-summary').val();
    var inputTags        = $('#input-tags').val();
    var inputDescription = $('#input-description').val();
    var inputDeleteCheck = $('#input-delete-check').val();

    // 入力値検証
    // タイトルが入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputTitle) {
      showErrorMessage('タイトルが入力されていません');
      return false;
    }
    // タイトルが長すぎる場合エラーメッセージを表示して送信を中断
    if (inputTitle.length > <?=$GAME_CONFIG['ROOM_TITLE_MAX_LENGTH']?>) {
      showErrorMessage('タイトルが長すぎます');
      return false;
    }

    // サマリーが長すぎる場合エラーメッセージを表示して送信を中断
    if (inputSummary.length > <?=$GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH']?>) {
      showErrorMessage('サマリーが長すぎます');
      return false;
    }

    // タグの検証
    var tags = inputTags.split(' ');

    // タグの数が多すぎる場合エラーメッセージを表示して送信を中断
    if (tags.length > <?=$GAME_CONFIG['ROOM_TAG_MAX']?>) {
      showErrorMessage('タグの数が多すぎます');
      return false;
    }

    // タグに長すぎるものがある場合エラーメッセージを表示して送信を中断
    tags.forEach(function(tag) {
      if (tag.length > <?=$GAME_CONFIG['ROOM_TAG_MAX_LENGTH']?>) {
        showErrorMessage('文字数制限を超過したタグがあります');
        return false;
      }
    });

    // 説明文が長すぎる場合エラーメッセージを表示して送信を中断
    if (inputDescription.length > <?=$GAME_CONFIG['ROOM_DESCRIPTION_MAX_LENGTH']?>) {
      showErrorMessage('説明文が長すぎます');
      return false;
    }

    // トークルーム削除に何か入力されており、入力内容がDELETEではない場合エラーメッセージを表示して送信を中断
    if (inputDeleteCheck && inputDeleteCheck != 'DELETE') {
      showErrorMessage('トークルーム削除用の文言が一致しません。');
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