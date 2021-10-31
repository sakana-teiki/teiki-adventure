<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['category'])) { // 受け取ったデータにカテゴリがない場合は400(Bad Request)を返し処理を中断
      http_response_code(400);
      exit;
    }

    // カテゴリごとに処理を振り分け
    if ($_POST['category'] == 'notification') {
      // 通知の場合
      // 入力値検証
      // 以下の条件のうちいずれかを満たせば400(Bad Request)を返し処理を中断
      if (
        !isset($_POST['webhook']) || // 受け取ったデータにウェブフックURLがない
        ($_POST['webhook'] && strpos($_POST['webhook'], $GAME_CONFIG['DISCORD_WEBHOOK_PREFIX']) !== 0) || // ウェブフックURLが入力されており、URLの内容が不正（URLの先頭部分がおかしい）
        !validatePOST('notification_replied',                ['non-empty', 'boolean']) ||
        !validatePOST('notification_new_arrival',            ['non-empty', 'boolean']) ||
        !validatePOST('notification_faved',                  ['non-empty', 'boolean']) ||
        !validatePOST('notification_direct_message',         ['non-empty', 'boolean']) ||
        !validatePOST('notification_webhook_replied',        ['non-empty', 'boolean']) ||
        !validatePOST('notification_webhook_new_arrival',    ['non-empty', 'boolean']) ||
        !validatePOST('notification_webhook_faved',          ['non-empty', 'boolean']) ||
        !validatePOST('notification_webhook_direct_message', ['non-empty', 'boolean'])
      ) {
        http_response_code(400);
        exit;
      }

      // キャラクター情報のアップデート
      $statement = $GAME_PDO->prepare("
        UPDATE
          `characters`
        SET
          `webhook`                             = :webhook,
          `notification_replied`                = :notification_replied,
          `notification_new_arrival`            = :notification_new_arrival,
          `notification_faved`                  = :notification_faved,
          `notification_direct_message`         = :notification_direct_message,
          `notification_webhook_replied`        = :notification_webhook_replied,
          `notification_webhook_new_arrival`    = :notification_webhook_new_arrival,
          `notification_webhook_faved`          = :notification_webhook_faved,
          `notification_webhook_direct_message` = :notification_webhook_direct_message
        WHERE
          `ENo` = :ENo;
      ");

      $statement->bindParam(':ENo',                                 $_SESSION['ENo']);
      $statement->bindParam(':webhook',                             $_POST['webhook']);
      $statement->bindValue(':notification_replied',                $_POST['notification_replied']                == 'true', PDO::PARAM_BOOL);
      $statement->bindValue(':notification_new_arrival',            $_POST['notification_new_arrival']            == 'true', PDO::PARAM_BOOL);
      $statement->bindValue(':notification_faved',                  $_POST['notification_faved']                  == 'true', PDO::PARAM_BOOL);
      $statement->bindValue(':notification_direct_message',         $_POST['notification_direct_message']         == 'true', PDO::PARAM_BOOL);
      $statement->bindValue(':notification_webhook_replied',        $_POST['notification_webhook_replied']        == 'true', PDO::PARAM_BOOL);
      $statement->bindValue(':notification_webhook_new_arrival',    $_POST['notification_webhook_new_arrival']    == 'true', PDO::PARAM_BOOL);
      $statement->bindValue(':notification_webhook_faved',          $_POST['notification_webhook_faved']          == 'true', PDO::PARAM_BOOL);
      $statement->bindValue(':notification_webhook_direct_message', $_POST['notification_webhook_direct_message'] == 'true', PDO::PARAM_BOOL);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        http_response_code(500); 
        exit;
      }

      http_response_code(200); // ここまで全てOKなら200を返して処理を終了
      exit;
    } else if ($_POST['category'] == 'password') {
      // パスワード変更の場合
      // 入力値検証
      if (
        !validatePOST('currentPassword', ['non-empty']) ||
        !validatePOST('newPassword',     ['non-empty'])
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
    } else if ($_POST['category'] == 'delete') {
      // キャラクター削除の場合
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

      http_response_code(200); // ここまで全てOKなら200を返して処理を終了
      exit;
    } else {
      // カテゴリーが上記のどれでもない場合400(Bad Request)を返し処理を終了
      http_response_code(400);
      exit;
    }
  }
  
  // 対象キャラクターの取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `webhook`,
      `notification_replied`,
      `notification_new_arrival`,
      `notification_faved`,
      `notification_direct_message`,
      `notification_webhook_replied`,
      `notification_webhook_new_arrival`,
      `notification_webhook_faved`,
      `notification_webhook_direct_message`
    FROM
      `characters`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result    = $statement->execute();
  $character = $statement->fetch();

  if (!$result || !$character) {
    // SQLの実行や実行結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  $PAGE_SETTING['TITLE'] = '設定';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<?php
  if ($_SERVER['REQUEST_METHOD'] == 'GET') { // GETリクエスト時の処理
?>

<h1>設定</h1>

<p>
  各項目カテゴリごとに設定を行ってください。<br>
  各送信ボタンは各項目カテゴリごとに設定を反映するため、複数の項目カテゴリを変更していた場合一部の変更が適用されません。
</p>

<hr>

<section>
  <h2>通知</h2>

  <section class="form">
    <div class="form-title">通知</div>
    <div class="form-description">各タイミングで通知を受け取るかどうかを指定します。</div>
    <div><label><input id="notification-replied" type="checkbox" <?=$character['notification_replied'] ? 'checked' : ''?>> 返信された場合</label></div>
    <div><label><input id="notification-new-arrival" type="checkbox" <?=$character['notification_new_arrival'] ? 'checked' : ''?>> 購読中のトークルームに新着があった場合</label></div>
    <div><label><input id="notification-faved" type="checkbox" <?=$character['notification_faved'] ? 'checked' : ''?>> お気に入りされた場合</label></div>
    <div><label><input id="notification-direct-message" type="checkbox" <?=$character['notification_direct_message'] ? 'checked' : ''?>> ダイレクトメッセージを受け取った場合</label></div>
  </section>

  <section class="form">
    <div class="form-title">Discord通知</div>
    <div class="form-description">各タイミングでDiscordで通知を受け取るかどうかを指定します。Discord通知を行う場合、ウェブフックURLも合わせて指定してください。</div>
    <div><label><input id="notification-webhook-replied" type="checkbox" <?=$character['notification_webhook_replied'] ? 'checked' : ''?>> 返信された場合</label></div>
    <div><label><input id="notification-webhook-new-arrival" type="checkbox" <?=$character['notification_webhook_new_arrival'] ? 'checked' : ''?>> 購読中のトークルームに新着があった場合</label></div>
    <div><label><input id="notification-webhook-faved" type="checkbox" <?=$character['notification_webhook_faved'] ? 'checked' : ''?>> お気に入りされた場合</label></div>
    <div><label><input id="notification-webhook-direct-message" type="checkbox" <?=$character['notification_webhook_direct_message'] ? 'checked' : ''?>> ダイレクトメッセージを受け取った場合</label></div>
  </section>

  <section class="form">
    <div class="form-title">DiscordウェブフックURL</div>
    <input id="input-webhook" class="form-input-long" type="text" placeholder="<?=$GAME_CONFIG['DISCORD_WEBHOOK_PREFIX']?>..." value="<?=htmlspecialchars($character['webhook'])?>">
  </section>

  <div id="notification-error-message-area"></div>

  <div class="button-wrapper">
    <button id="notification-send-button" class="button">送信</button>
  </div>
</section>

<hr>

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

  <div id="password-error-message-area"></div>

  <div class="button-wrapper">
    <button id="password-send-button" class="button">送信</button>
  </div>
</section>

<hr>

<section>
  <h2>キャラクター削除</h2>
  <section class="form">
    <div class="form-description">キャラクター削除を行う場合は、以下の入力欄に「DELETE」と入力してください。</div>
    <input id="input-delete-check" class="form-input" type="text">
  </section>

  <div id="delete-error-message-area"></div>

  <div class="button-wrapper">
    <button id="delete-send-button" class="button">送信</button>
  </div>
</section>

<script src="<?=$GAME_CONFIG['URI']?>scripts/jssha-sha256.js"></script>
<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）
  var hashStretch = <?=$GAME_CONFIG['CLIENT_HASH_STRETCH']?>; // ハッシュ化の回数
  var hashSalt    = '<?=$GAME_CONFIG['CLIENT_HASH_SALT']?>';  // ハッシュ化の際のsalt

  // エラーメッセージを表示する関数及びその関連処理
  function showErrorMessage(category, message) {

    var errorMessageArea = null;
    errorMessageArea = $('#'+category+'-error-message-area');

    errorMessageArea.empty();
    errorMessageArea.append(
      '<div class="message-banner message-banner-error">'+
        message +
      '</div>'
    );
  }

  // 通知
  $('#notification-send-button').on('click', function() {
    // 各種の値を取得
    var inputWebhook = $('#input-webhook').val();

    // 入力値検証
    // ウェブフックURLが入力されており、形式が不正（URLの先頭部分が不適）な場合エラーメッセージを表示して処理を中断
    if (inputWebhook && inputWebhook.indexOf('<?=$GAME_CONFIG['DISCORD_WEBHOOK_PREFIX']?>') !== 0) {
      showErrorMessage('notification', 'ウェブフックURLの形式が不正です');
      return;
    }

    waitingResponse = true; // レスポンス待ち状態をONに

    $.post(location.href, { // このページのURLにPOST送信
      csrf_token: '<?=$_SESSION['token']?>',
      category:   'notification',
      webhook:    inputWebhook,
      notification_replied:        $('#notification-replied'       ).prop('checked') == true,
      notification_new_arrival:    $('#notification-new-arrival'   ).prop('checked') == true,
      notification_faved:          $('#notification-faved'         ).prop('checked') == true,
      notification_direct_message: $('#notification-direct-message').prop('checked') == true,
      notification_webhook_replied:        $('#notification-webhook-replied'       ).prop('checked') == true,
      notification_webhook_new_arrival:    $('#notification-webhook-new-arrival'   ).prop('checked') == true,
      notification_webhook_faved:          $('#notification-webhook-faved'         ).prop('checked') == true,
      notification_webhook_direct_message: $('#notification-webhook-direct-message').prop('checked') == true
    }).done(function() {
      location.reload(); // 完了時にリロード
    }).fail(function() { 
      showErrorMessage('notification', '処理中にエラーが発生しました'); // エラーが発生した場合エラーメッセージを表示
    }).always(function() {
      waitingResponse = false;  // 接続終了後はレスポンス待ち状態を解除
    });
  });

  // パスワード変更
  $('#password-send-button').on('click', function() {
    // 各種の値を取得
    var inputCurrentPassword = $('#input-current-password').val();
    var inputNewPassword     = $('#input-new-password').val();
    var inputConfirm         = $('#input-confirm').val();
    
    // 入力値検証
    // 現在のパスワードが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputCurrentPassword) {
      showErrorMessage('password', '現在のパスワードが入力されていません');
      return;
    }
    // 現在のパスワードが短すぎる場合エラーメッセージを表示して処理を中断
    if (inputCurrentPassword.length < <?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>) {
      showErrorMessage('password', '現在のパスワードが短すぎます');
      return;
    }

    // 新しいパスワードが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputNewPassword) {
      showErrorMessage('password', '新しいパスワードが入力されていません');
      return;
    }
    // 新しいパスワードが短すぎる場合エラーメッセージを表示して処理を中断
    if (inputNewPassword.length < <?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>) {
      showErrorMessage('password', '新しいパスワードが短すぎます');
      return;
    }

    // パスワード再入力が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputConfirm) {
      showErrorMessage('password', '新しいパスワード再入力が入力されていません');
      return;
    }
    // 再入力したパスワードが一致しない場合エラーメッセージを表示して処理を中断
    if (inputNewPassword != inputConfirm) {
      showErrorMessage('password', '新しいパスワードと再入力の内容が一致しません');
      return;
    }

    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
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

    waitingResponse = true; // レスポンス待ち状態をONに

    $.post(location.href, { // このページのURLにPOST送信
      csrf_token:      '<?=$_SESSION['token']?>',
      category:        'password',
      currentPassword: hashedCurrentPassword,
      newPassword:     hashedNewPassword
    }).done(function() {
      location.reload(); // 完了時にリロード
    }).fail(function() { 
      showErrorMessage('password', '処理中にエラーが発生しました'); // エラーが発生した場合エラーメッセージを表示
    }).always(function() {
      waitingResponse = false;  // 接続終了後はレスポンス待ち状態を解除
    });
  });

  // キャラクター削除  
  $('#delete-send-button').on('click', function() {
    // 削除チェック用の入力欄の値を取得
    var inputDeleteCheck = $('#input-delete-check').val();

    // 入力内容がDELETEではない場合は処理を中断
    if (inputDeleteCheck != 'DELETE') {
      showErrorMessage('delete', 'キャラクター削除用の文言が一致しません。');
      return;
    }

    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return;
    }

    waitingResponse = true; // レスポンス待ち状態をONに

    $.post(location.href, { // このページのURLにPOST送信
      csrf_token: '<?=$_SESSION['token']?>',
      category:   'delete'
    }).done(function() {
      location.href = '<?=$GAME_CONFIG['TOP_URI']?>'; // 完了時にトップに遷移
    }).fail(function() {
      showErrorMessage('delete', '処理中にエラーが発生しました'); // エラーが発生した場合エラーメッセージを表示
    }).always(function() {
      waitingResponse = false;  // 接続終了後はレスポンス待ち状態を解除
    });
  });
</script>

<?php
  }
?>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>