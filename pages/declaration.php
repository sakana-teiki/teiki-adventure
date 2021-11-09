<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // ゲームステータスを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `update_status`,
      `next_update_nth`
    FROM
      `game_status`;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result     = $statement->execute();
  $gameStatus = $statement->fetch();

  if (!$result || !$gameStatus) {
    responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  // POSTの場合
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (!validatePOST('diary', ['disallow-special-chars'])) {
      responseError(400);
    }

    if (!$gameStatus['update_status']) {
      responseError(403); // 更新未確定状態で行動宣言しようとしていた場合403(Forbidden)を返して処理を中断
    }
    
    // 宣言状況の作成あるいは反映
    $statement = $GAME_PDO->prepare("
      INSERT INTO `characters_declarations` (
        `ENo`,
        `nth`,
        `diary`
      ) VALUES (
        :ENo,
        :nth,
        :diary
      )

      ON DUPLICATE KEY UPDATE
        `diary` = :diary;
    ");

    $statement->bindParam(':ENo',   $_SESSION['ENo']);
    $statement->bindParam(':nth',   $gameStatus['next_update_nth']);
    $statement->bindParam(':diary', $_POST['diary']);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // 失敗した場合は500(Internal Server Error)を返して処理を中断
    }
  }

  // 宣言内容を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `diary`
    FROM
      `characters_declarations`
    WHERE
      `ENo` = :ENo AND
      `nth` = :nth;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);
  $statement->bindParam(':nth', $gameStatus['next_update_nth']);

  $result      = $statement->execute();
  $declaration = $statement->fetch();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  if (!$declaration) { // 未宣言の場合適切なデフォルト値を設定
    $declaration = array(
      'diary' => ''
    );
  }

  $PAGE_SETTING['TITLE'] = '行動宣言';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>行動宣言</h1>

<form id="form" method="post">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

  <h2>行動内容</h2>
    
  <section class="form">
    <div class="form-title">日記（<?=$GAME_CONFIG['DIARY_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      日記はプロフィールと同様の書式で装飾することができます。<br>
      詳しくはルールブックを確認してください。
    </div>
    <textarea id="input-diary" name="diary" class="form-textarea" placeholder="日記"><?=htmlspecialchars($declaration['diary'])?></textarea>
  </section>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button class="button" type="submit">反映</button>
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

  $('#form').submit(function(){
    // 各種の値を取得
    var inputDiary = $('#input-diary').val();

    // 入力値検証
    // 日記が長すぎる場合エラーメッセージを表示して送信を中断
    if (inputDiary.length > <?=$GAME_CONFIG['DIARY_MAX_LENGTH']?>) {
      showErrorMessage('日記が長すぎます');
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