<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('ap', ['non-empty', 'natural-number'])
    ) {
      responseError(400);
    }

    // 配布AP量に加算
    $statement = $GAME_PDO->prepare("
      UPDATE
        `game_status`
      SET
        `AP` = `AP` + :ap;
    ");

    $statement->bindParam(':ap', $_POST['ap'], PDO::PARAM_INT);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
    }
  }

  $PAGE_SETTING['TITLE'] = 'AP配布';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>AP配布</h1>

<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
<section>
  <h2>実行結果</h2>

  <p>
    APを<?=$_POST['ap']?>配布しました。 
  </p>
</section>
<?php } ?>

<section>
  <h2>配布数</h2>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <section class="form">
      <div class="form-title">AP配布数</div>
      <input id="input-ap" class="form-input" type="number" name="ap" placeholder="AP配布数" min="1" value="1">
    </section>

    <div id="error-message-area"></div>

    <div class="button-wrapper">
      <button class="button" type="submit">配布</button>
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
    var inputAP = $('#input-ap').val();

    // 入力値検証
    // AP配布数が入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputAP) {
      showErrorMessage('AP配布数が入力されていません。');
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