<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('title',       ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['ROOM_TITLE_MAX_LENGTH'])       ||
      !validatePOST('description', [                            'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['ROOM_DESCRIPTION_MAX_LENGTH']) ||
      !validatePOST('summary',     [             'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH'])     ||
      !validatePOST('tags',        [             'single-line'])
    ) {
      responseError(400);
    }

    // DB登録処理
    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // トークルームの登録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `rooms` (
        `administrator`,
        `title`,
        `summary`,
        `description`
      ) VALUES (
        :administrator,
        :title,
        :summary,
        :description
      );
    ");

    $statement->bindParam(':administrator', $_SESSION['ENo']);
    $statement->bindParam(':title',         $_POST['title']);
    $statement->bindParam(':summary',       $_POST['summary']);
    $statement->bindParam(':description',   $_POST['description']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack(); // DBへの登録に失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
      responseError(500);
    }

    $lastInsertId = intval($GAME_PDO->lastInsertId()); // 登録されたidを取得

    // RNo情報の登録（登録されたトークルームのうち、公共トークルームを除いた指定のid以下のトークルームがいくつ存在しているか？の数をRNoとする。）
    $statement = $GAME_PDO->prepare("
      UPDATE
        `rooms`
      SET
        `RNo` = (
          SELECT 
           *
          FROM (
            SELECT
              COUNT(*)
            FROM
              `rooms`
            WHERE
              `administrator` IS NOT NULL AND `id` <= :id
          ) `r`
        )
      WHERE
        `id` = :id;
    ");

    $statement->bindParam(':id', $lastInsertId);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack(); // 失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
      responseError(500);
    }

    // 登録されたRNoの取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `RNo`
      FROM
        `rooms`
      WHERE
        `id` = :id;
    ");

    $statement->bindParam(':id', $lastInsertId);

    $result = $statement->execute();
    $room   = $statement->fetch();

    if (!$result || !$room) {
      $GAME_PDO->rollBack(); // 失敗あるいは結果が存在しない場合は500(Internal Server Error)を返してロールバックし、処理を中断
      responseError(500);
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

      $statement->bindParam(':RNo', $room['RNo']);
      $statement->bindParam(':tag', $tag);

      $result = $statement->execute();
      
      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500);
      }
    }

    // ここまで全て成功した場合はコミットしてリダイレクト
    $GAME_PDO->commit();
    header('Location:'.$GAME_CONFIG['URI'].'room?room='.$room['RNo'], true, 302);
    exit;
  }

  $PAGE_SETTING['TITLE'] = 'トークルーム作成';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>トークルーム作成</h1>

<form id="create-room-form" method="post">
  <h2>作成内容</h2>

  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

  <section class="form">
    <div class="form-title">タイトル（<?=$GAME_CONFIG['ROOM_TITLE_MAX_LENGTH']?>文字まで）</div>
    <input id="input-title" class="form-input" type="text" name="title" placeholder="タイトル">
  </section>

  <section class="form">
    <div class="form-title">タグ（<?=$GAME_CONFIG['ROOM_TAG_MAX']?>個、各タグ<?=$GAME_CONFIG['ROOM_TAG_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      半角スペースで区切ることで複数指定できます。
    </div>
    <input id="input-tags" class="form-input-long" type="text" name="tags" placeholder="タグ">
  </section>

  <section class="form">
    <div class="form-title">サマリー（<?=$GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      トークルーム一覧で表示される短い文章です。
    </div>
    <input id="input-summary" class="form-input-long" type="text" name="summary" placeholder="サマリー">
  </section>

  <section class="form">
    <div class="form-title">説明文（<?=$GAME_CONFIG['ROOM_DESCRIPTION_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      説明文はプロフィールと同様の書式で装飾することができます。<br>
      詳しくはルールブックを確認してください。
    </div>
    <textarea id="input-description" class="form-textarea" type="text" name="description" placeholder="説明文"></textarea>
  </section>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button class="button" type="submit">作成</button>
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

  $('#create-room-form').submit(function(){
    // 値を取得
    var inputTitle       = $('#input-title').val();
    var inputSummary     = $('#input-summary').val();
    var inputTags        = $('#input-tags').val();
    var inputDescription = $('#input-description').val();

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