<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    // 以下の条件のうちいずれかを満たせば400(Bad Request)を返し処理を中断
    if (
      !isset($_POST['title'])       || // 受け取ったデータにタイトルがない
      !isset($_POST['tags'])        || // 受け取ったデータにタグがない
      !isset($_POST['summary'])     || // 受け取ったデータにサマリーがない
      !isset($_POST['description']) || // 受け取ったデータに説明文がない
      $_POST['title'] == ''         || // 受け取ったタイトルが空文字列
      mb_strlen($_POST['title'])   > $GAME_CONFIG['TITLE_MAX_LENGTH']     || // 受け取ったタイトルが長すぎる
      mb_strlen($_POST['summary']) > $GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH'] // 受け取ったサマリーが長すぎる
    ) {
      http_response_code(400);
      exit;
    }

    // DB登録処理
    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // トークルームの登録
    $statement = $GAME_PDO->prepare('
      INSERT INTO `rooms` (
        `administrator`, `title`, `summary`, `description`
      ) VALUES (
        :administrator, :title, :summary, :description
      );
    ');

    $statement->bindParam(':administrator', $_SESSION['ENo']);
    $statement->bindParam(':title',         $_POST['title']);
    $statement->bindParam(':summary',       $_POST['summary']);
    $statement->bindParam(':description',   $_POST['description']);

    $result = $statement->execute();

    if (!$result) {
      http_response_code(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
      $GAME_PDO->rollBack();
      exit;
    }

    $RNo = intval($GAME_PDO->lastInsertId()); // RNoを取得

    // タグの登録
    $tags = explode(' ', $_POST['tags']);
    $tags = array_filter($tags, "strlen"); // 空行の要素は削除する

    foreach ($tags as $tag) {
      $statement = $GAME_PDO->prepare('
        INSERT INTO `rooms_tags` (
          `RNo`, `tag`
        ) VALUES (
          :RNo, :tag
        );
      ');

      $statement->bindParam(':RNo', $RNo);
      $statement->bindParam(':tag', $tag);

      $result = $statement->execute();
      
      if (!$result) {
        http_response_code(500);
        $GAME_PDO->rollBack();
        exit;
      }
    }

    // ここまで全て成功した場合はコミットしてリダイレクト
    $GAME_PDO->commit();
    header('Location:'.$GAME_CONFIG['URI'].'room?RNo='.$RNo, true, 302);
    exit;
  }

  $PAGE_SETTING['TITLE'] = 'トークルーム作成';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<form id="create-room-form" method="post">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

  <h2>トークルーム作成</h2>

  <section class="form">
    <div class="form-title">タイトル（<?=$GAME_CONFIG['TITLE_MAX_LENGTH']?>文字まで）</div>
    <input id="input-title" class="form-input" type="text" name="title" placeholder="タイトル">
  </section>

  <section class="form">
    <div class="form-title">タグ</div>
    <div class="form-description">
      半角スペースで区切ることで複数指定できます。
    </div>
    <input class="form-input-long" type="text" name="tags" placeholder="タグ">
  </section>

  <section class="form">
    <div class="form-title">サマリー（<?=$GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      トークルーム一覧で表示される短い文章です。
    </div>
    <input id="input-summary" class="form-input-long" type="text" name="summary" placeholder="サマリー">
  </section>

  <section class="form">
    <div class="form-title">説明文</div>
    <div class="form-description">
      説明文はプロフィールと同様の書式で装飾することができます。<br>
      詳しくはルールブックを確認してください。
    </div>
    <textarea class="form-textarea" type="text" name="description" placeholder="説明文"></textarea>
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
    var inputTitle   = $('#input-title').val();
    var inputSummary = $('#input-summary').val();

    // 入力値検証
    // タイトルが入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputTitle) {
      showErrorMessage('タイトルが入力されていません');
      return false;
    }
    // タイトルが長すぎる場合エラーメッセージを表示して送信を中断
    if (inputTitle.length > <?=$GAME_CONFIG['TITLE_MAX_LENGTH']?>) {
      showErrorMessage('タイトルが長すぎます');
      return false;
    }

    // サマリーが長すぎる場合エラーメッセージを表示して送信を中断
    if (inputSummary.length > <?=$GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH']?>) {
      showErrorMessage('サマリーが長すぎます');
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