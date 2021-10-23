<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // パスワード変更を行う場合
    if (isset($_POST['changePassword']) && $_POST['changePassword']) {
      // 入力値検証
      // 以下の条件のうちいずれかを満たせば400(Bad Request)を返し処理を中断
      if (
          $_POST['currentPassword'] == '' || // 受け取った現在のパスワードが空文字列
          $_POST['newPassword']    == ''    // 受け取った新しいパスワードが空文字列
      ) {
        http_response_code(400);
        exit;
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
        http_response_code(500); 
        exit;
      }

      if (!password_verify($_POST['currentPassword'], $data['password'])) {
        // パスワードの照合を行い合致しない場合は403(Forbidden)を返し処理を中断
        http_response_code(403);
        exit;
      }

      // 新しいパスワードのサーバー側ハッシュ化
      $newPassword = password_hash($_POST['newPassword'], PASSWORD_DEFAULT);

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
        http_response_code(500); 
        exit;
      }

      http_response_code(200); // ここまで全てOKなら200を返して処理を終了
      exit;
    }
  
    // キャラクター削除を行う場合
    if (isset($_POST['deleteCharacter']) && $_POST['deleteCharacter']) {
      // 削除フラグをONに
      $statement = $GAME_PDO->prepare("
        UPDATE
          `characters`
        SET
          `deleted` = true
        WHERE
          `ENo` = :ENo;
      ");

      $statement->bindParam(':ENo', $_SESSION['ENo']);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        http_response_code(500); 
        exit;
      }

      // セッションの破棄
      $_SESSION = array();
      session_destroy();
    }
  }

  $PAGE_SETTING['TITLE'] = '設定';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<?php
  if ($_SERVER['REQUEST_METHOD'] == 'GET') { // GETリクエスト時の処理
?>

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
</section>
<section>
  <h2>キャラクター削除</h2>
  <section class="form">
    <div class="form-description">キャラクター削除を行う場合は、以下の入力欄に「DELETE」と入力してください。</div>
    <input id="input-delete-check" class="form-input" type="text">
  </section>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button id="send-button" class="button">送信</button>
  </div>
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

　$('#send-button').on('click', function() {
    // 各種の値を取得
    var inputCurrentPassword = $('#input-current-password').val();
    var inputNewPassword     = $('#input-new-password').val();
    var inputConfirm         = $('#input-confirm').val();
    var inputDeleteCheck     = $('#input-delete-check').val();

    // 送信内容を管理するオブジェクト
    var sendDatas = {};
    
    // 現在のパスワード、新しいパスワード、再入力のいずれかが入力されている場合パスワード変更するものと判断
    if (inputCurrentPassword || inputNewPassword || inputConfirm) {
      // 入力値検証
      // 現在のパスワードが入力されていない場合エラーメッセージを表示して処理を中断
      if (!inputCurrentPassword) {
        showErrorMessage('現在のパスワードが入力されていません');
        return;
      }
      // 現在のパスワードが短すぎる場合エラーメッセージを表示して処理を中断
      if (inputCurrentPassword.length < <?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>) {
        showErrorMessage('現在のパスワードが短すぎます');
        return;
      }

      // 新しいパスワードが入力されていない場合エラーメッセージを表示して処理を中断
      if (!inputNewPassword) {
        showErrorMessage('新しいパスワードが入力されていません');
        return;
      }
      // 新しいパスワードが短すぎる場合エラーメッセージを表示して処理を中断
      if (inputNewPassword.length < <?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>) {
        showErrorMessage('新しいパスワードが短すぎます');
        return;
      }

      // パスワード再入力が入力されていない場合エラーメッセージを表示して処理を中断
      if (!inputConfirm) {
        showErrorMessage('新しいパスワード再入力が入力されていません');
        return;
      }
      // 再入力したパスワードが一致しない場合エラーメッセージを表示して処理を中断
      if (inputNewPassword != inputConfirm) {
        showErrorMessage('新しいパスワードと再入力の内容が一致しません');
        return;
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

      // 送信内容管理用オブジェクトに送信内容を追加
      sendDatas.changePassword  = true;
      sendDatas.currentPassword = hashedCurrentPassword;
      sendDatas.newPassword     = hashedNewPassword;
    }

    // キャラクター削除に何か入力されている場合はキャラクターを削除したいものと判断
    if (inputDeleteCheck) {
      // 入力内容がDELETEではない場合は処理を中断
      if (inputDeleteCheck != 'DELETE') {
        showErrorMessage('キャラクター削除用の文言が一致しません。');
        return;
      }

      // 送信内容管理用オブジェクトに送信内容を追加
      sendDatas.deleteCharacter = true;
    }

    // 特に送る内容がない場合、エラーメッセージを削除して処理を中断
    if (!Object.keys(sendDatas).length) {
      errorMessageArea.empty();
      return;
    }

    // 送信
    // 送信内容にCSRFトークンを追加
    sendDatas['csrf_token'] = '<?=$_SESSION['token']?>';

    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return;
    }

    waitingResponse = true; // レスポンス待ち状態をONに
    $.post(location.href, sendDatas // このページのURLにPOST送信
     ).done(function() {
      if (sendDatas.deleteCharacter) { // キャラ削除を行っていた場合、完了時にトップに遷移
        location.href = '<?=$GAME_CONFIG['TOP_URI']?>';
      } else { // そうでなければリロード
        location.reload();
      }
    }).fail(function() { 
      showErrorMessage('処理中にエラーが発生しました'); // エラーが発生した場合エラーメッセージを表示
    }).always(function() {
      waitingResponse = false;  // 接続終了後はレスポンス待ち状態を解除
    });
  });
</script>

<?php
  }
?>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>