<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('eno',    ['non-empty', 'natural-number']) ||
      !validatePOST('number', ['non-empty', 'natural-number'])
    ) {
      http_response_code(400);
      exit;
    }

    // キャラクターのアイコン上限の付与
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `additional_icons` = `additional_icons` + :number
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':number', $_POST['number'], PDO::PARAM_INT);
    $statement->bindParam(':ENo',    $_POST['eno']);

    $result = $statement->execute();

    if (!$result) {
      http_response_code(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
      exit;
    }
  }

  $PAGE_SETTING['TITLE'] = 'パスワード再発行';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>パスワード再発行</h1>

<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
<section>
  <h2>実行結果</h2>

  <p>
    ENo.<?=$_POST['eno']?>にアイコン上限数が<?=$_POST['number']?>付与されました。 
  </p>
</section>
<?php } ?>

<section>
  <h2>アイコン上限付与対象</h2>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    
    <section class="form">
      <div class="form-title">ENo</div>
      <div class="form-description">アイコン上限付与を行うキャラクターのENoを指定します。</div>
      <input id="input-eno" class="form-input" type="number" name="eno" placeholder="ENo">
      <button id="check-eno" type="button" class="form-check-button">対象を確認</button>
    </section>

    <section class="form">
      <div class="form-title">アイコン上限付与数</div>
      <div class="form-description">アイコン上限の付与数を指定します。</div>
      <input id="input-additional-icons" class="form-input" type="number" name="number" placeholder="アイコン上限付与数">
    </section>

    <div id="error-message-area"></div>

    <div class="button-wrapper">
      <button class="button" type="submit">付与</button>
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
    var inputENo             = $('#input-eno').val();
    var inputAdditionalIcons = $('#input-additional_icons').val();

    // 入力値検証
    // ENoが入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputENo) {
      showErrorMessage('ENoが入力されていません');
      return false;
    }

    // 入力値検証
    // ENoが入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputENo) {
      showErrorMessage('アイコン上限付与数が入力されていません。');
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