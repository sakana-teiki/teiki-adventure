<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';  

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('current_password', ['non-empty']) ||
      !validatePOST('new_password',     ['non-empty'])
    ) {
      responseError(400);
    }

    // キャラクターデータを取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `ENo`, `password`
      FROM
        `characters`
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();
    $data   = $statement->fetch();

    if (!$result || !$data) {
      // SQLの実行やデータの取得に失敗した場合は500(Internal Server Error)を返し処理を中断
      responseError(500);
    }

    if (!password_verify($_POST['current_password'], $data['password'])) {
      // パスワードの照合を行い合致しない場合は403(Forbidden)を返し処理を中断
      responseError(403);
    }

    // 新しいパスワードのサーバー側ハッシュ化
    $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    // パスワードのアップデート
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `password` = :password
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':password', $newPassword);
    $statement->bindParam(':ENo',      $_SESSION['ENo']);

    $result = $statement->execute();

    if (!$result) {
      // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      responseError(500);
    }
  }

  $PAGE_SETTING['TITLE'] = 'パスワード変更';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>ゲーム設定</h1>

<section>
  <h2>パスワード変更</h2>

  <section class="form">
    <div class="form-title">現在のパスワード</div>
    <input id="input-current-password" class="form-input" type="password" placeholder="現在のパスワード">
  </section>

  <section class="form">
    <div class="form-title">新しいパスワード（<?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>文字以上）</div>
    <input id="input-new-password" class="form-input" type="password" placeholder="新しいパスワード">
  </section>

  <section class="form">
    <div class="form-title">新しいパスワード再入力</div>
    <input id="input-confirm" class="form-input" type="password" placeholder="再入力">
  </section>

  <div id="error-message-area"></div>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input id="input-hidden-current-password" type="hidden" name="current_password">
    <input id="input-hidden-new-password" type="hidden" name="new_password">

    <div class="button-wrapper">
      <button class="button">送信</button>
    </div>
  </form>
</section>

<script src="<?=$GAME_CONFIG['URI']?>scripts/jssha-sha256.js"></script>
<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）
  var hashStretch = <?=$GAME_CONFIG['CLIENT_HASH_STRETCH']?>; // ハッシュ化の回数
  var hashSalt    = '<?=$GAME_CONFIG['CLIENT_HASH_SALT']?>';  // ハッシュ化の際のsalt

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
    var inputCurrentPassword = $('#input-current-password').val();
    var inputNewPassword     = $('#input-new-password').val();
    var inputConfirm         = $('#input-confirm').val();
    
    // 入力値検証
    // 現在のパスワードが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputCurrentPassword) {
      showErrorMessage('現在のパスワードが入力されていません');
      return false;
    }
    // 現在のパスワードが短すぎる場合エラーメッセージを表示して処理を中断
    if (inputCurrentPassword.length < <?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>) {
      showErrorMessage('現在のパスワードが短すぎます');
      return false;
    }

    // 新しいパスワードが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputNewPassword) {
      showErrorMessage('新しいパスワードが入力されていません');
      return false;
    }
    // 新しいパスワードが短すぎる場合エラーメッセージを表示して処理を中断
    if (inputNewPassword.length < <?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>) {
      showErrorMessage('新しいパスワードが短すぎます');
      return false;
    }

    // パスワード再入力が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputConfirm) {
      showErrorMessage('新しいパスワード再入力が入力されていません');
      return false;
    }
    // 再入力したパスワードが一致しない場合エラーメッセージを表示して処理を中断
    if (inputNewPassword != inputConfirm) {
      showErrorMessage('新しいパスワードと再入力の内容が一致しません');
      return false;
    }

    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 現在のパスワードのハッシュ化
    var hashedCurrentPassword = inputCurrentPassword + hashSalt; // ソルティング
    for (var i = 0; i < hashStretch; i++) { // ストレッチング
      var shaObj = new jsSHA("SHA-256", "TEXT");
      shaObj.update(hashedCurrentPassword);
      hashedCurrentPassword = shaObj.getHash("HEX");
    }

    // 新しいパスワードのハッシュ化
    var hashedNewPassword = inputNewPassword + hashSalt; // ソルティング
    for (var i = 0; i < hashStretch; i++) { // ストレッチング
      var shaObj = new jsSHA("SHA-256", "TEXT");
      shaObj.update(hashedNewPassword);
      hashedNewPassword = shaObj.getHash("HEX");
    }

    // 送信
    $('#input-hidden-current-password').val(hashedCurrentPassword);
    $('#input-hidden-new-password').val(hashedNewPassword);

    waitingResponse = true; // レスポンス待ち状態をONに
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>