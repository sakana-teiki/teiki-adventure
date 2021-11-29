<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';  

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ウェブフックURLが正しいものか判定する関数を定義
    function isAcceptableWebhookURL($url) {
      global $GAME_CONFIG;
    
      foreach ($GAME_CONFIG['WEBHOOK_ACCEPTABLE_PREFIXES'] as $prefix) {
        if (strpos($url, $prefix) === 0) {
          return true;
        }
      }
        
      return false;
    }

    // 入力値検証
    // 以下の条件のうちいずれかを満たせば400(Bad Request)を返し処理を中断
    if (
      !validatePOST('delete_check', []) || // 受け取ったデータに削除チェックがない
      !validatePOST('webhook',      []) || // 受け取ったデータにウェブフックURLがない
      ($_POST['webhook'] && !isAcceptableWebhookURL($_POST['webhook'])) // ウェブフックURLが入力されており、URLの内容が不正（URLの先頭部分がおかしい）
    ) {
      responseError(400);
    }

    // キャラクター情報のアップデート
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `deleted`                             = :deleted,
        `webhook`                             = :webhook,
        `notification_replied`                = :notification_replied,
        `notification_new_arrival`            = :notification_new_arrival,
        `notification_faved`                  = :notification_faved,
        `notification_direct_message`         = :notification_direct_message,
        `notification_trade`                  = :notification_trade,
        `notification_flea_market`            = :notification_flea_market,
        `notification_webhook_replied`        = :notification_webhook_replied,
        `notification_webhook_new_arrival`    = :notification_webhook_new_arrival,
        `notification_webhook_faved`          = :notification_webhook_faved,
        `notification_webhook_direct_message` = :notification_webhook_direct_message,
        `notification_webhook_trade`          = :notification_webhook_trade,
        `notification_webhook_flea_market`    = :notification_webhook_flea_market
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo',                                 $_SESSION['ENo']);
    $statement->bindParam(':webhook',                             $_POST['webhook']);
    $statement->bindValue(':deleted',                             $_POST['delete_check'] == 'DELETE',                   PDO::PARAM_BOOL);
    $statement->bindValue(':notification_replied',                isset($_POST['notification_replied']),                PDO::PARAM_BOOL);
    $statement->bindValue(':notification_new_arrival',            isset($_POST['notification_new_arrival']),            PDO::PARAM_BOOL);
    $statement->bindValue(':notification_faved',                  isset($_POST['notification_faved']),                  PDO::PARAM_BOOL);
    $statement->bindValue(':notification_direct_message',         isset($_POST['notification_direct_message']),         PDO::PARAM_BOOL);
    $statement->bindValue(':notification_trade',                  isset($_POST['notification_trade']),                  PDO::PARAM_BOOL);
    $statement->bindValue(':notification_flea_market',            isset($_POST['notification_flea_market']),            PDO::PARAM_BOOL);
    $statement->bindValue(':notification_webhook_replied',        isset($_POST['notification_webhook_replied']),        PDO::PARAM_BOOL);
    $statement->bindValue(':notification_webhook_new_arrival',    isset($_POST['notification_webhook_new_arrival']),    PDO::PARAM_BOOL);
    $statement->bindValue(':notification_webhook_faved',          isset($_POST['notification_webhook_faved']),          PDO::PARAM_BOOL);
    $statement->bindValue(':notification_webhook_direct_message', isset($_POST['notification_webhook_direct_message']), PDO::PARAM_BOOL);
    $statement->bindValue(':notification_webhook_trade',          isset($_POST['notification_webhook_trade']),          PDO::PARAM_BOOL);
    $statement->bindValue(':notification_webhook_flea_market',    isset($_POST['notification_webhook_flea_market']),    PDO::PARAM_BOOL);

    $result = $statement->execute();

    if (!$result) {
      // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      responseError(500);
    }

    if ($_POST['delete_check'] == 'DELETE') {
      // 削除処理を行った場合
      // セッションの破棄
      $_SESSION = array();
      session_destroy();

      header('Location:'.$GAME_CONFIG['TOP_URI'], true, 302); // トップにリダイレクト
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
      `notification_trade`,
      `notification_flea_market`,
      `notification_webhook_replied`,
      `notification_webhook_new_arrival`,
      `notification_webhook_faved`,
      `notification_webhook_direct_message`,
      `notification_webhook_trade`,
      `notification_webhook_flea_market`
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
    responseError(500);
  }

  $PAGE_SETTING['TITLE'] = 'ゲーム設定';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>ゲーム設定</h1>

<section>
  <h2>パスワード変更</h2>

  <p>パスワード変更は<a href="<?=$GAME_CONFIG['URI']?>setting/password">こちら</a>から行えます。</p>
</section>

<form id="form" method="post">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

  <section>
    <h2>通知</h2>

    <section class="form">
      <div class="form-title">通知</div>
      <div class="form-description">各タイミングで通知を受け取るかどうかを指定します。</div>
      <div><label><input name="notification_replied" type="checkbox" <?=$character['notification_replied'] ? 'checked' : ''?>> 返信された場合</label></div>
      <div><label><input name="notification_new_arrival" type="checkbox" <?=$character['notification_new_arrival'] ? 'checked' : ''?>> 購読中のトークルームに新着があった場合</label></div>
      <div><label><input name="notification_faved" type="checkbox" <?=$character['notification_faved'] ? 'checked' : ''?>> お気に入りされた場合</label></div>
      <div><label><input name="notification_direct_message" type="checkbox" <?=$character['notification_direct_message'] ? 'checked' : ''?>> ダイレクトメッセージを受け取った場合</label></div>
      <div><label><input name="notification_trade" type="checkbox" <?=$character['notification_trade'] ? 'checked' : ''?>> アイテムトレードで何かアクションがあった場合</label></div>
      <div><label><input name="notification_flea_market" type="checkbox" <?=$character['notification_flea_market'] ? 'checked' : ''?>> フリーマーケットで何かアクションがあった場合</label></div>
    </section>

    <section class="form">
      <div class="form-title">Discord通知</div>
      <div class="form-description">各タイミングでDiscordで通知を受け取るかどうかを指定します。Discord通知を行う場合、ウェブフックURLも合わせて指定してください。</div>
      <div><label><input name="notification_webhook_replied" type="checkbox" <?=$character['notification_webhook_replied'] ? 'checked' : ''?>> 返信された場合</label></div>
      <div><label><input name="notification_webhook_new_arrival" type="checkbox" <?=$character['notification_webhook_new_arrival'] ? 'checked' : ''?>> 購読中のトークルームに新着があった場合</label></div>
      <div><label><input name="notification_webhook_faved" type="checkbox" <?=$character['notification_webhook_faved'] ? 'checked' : ''?>> お気に入りされた場合</label></div>
      <div><label><input name="notification_webhook_direct_message" type="checkbox" <?=$character['notification_webhook_direct_message'] ? 'checked' : ''?>> ダイレクトメッセージを受け取った場合</label></div>
      <div><label><input name="notification_webhook_trade" type="checkbox" <?=$character['notification_webhook_trade'] ? 'checked' : ''?>> アイテムトレードで何かアクションがあった場合</label></div>
      <div><label><input name="notification_webhook_flea_market" type="checkbox" <?=$character['notification_webhook_flea_market'] ? 'checked' : ''?>> フリーマーケットで何かアクションがあった場合</label></div>
    </section>

    <section class="form">
      <div class="form-title">DiscordウェブフックURL</div>
      <input id="input-webhook" name="webhook" class="form-input-long" type="text" placeholder="<?=$GAME_CONFIG['WEBHOOK_ACCEPTABLE_PREFIXES'][0]?>..." value="<?=htmlspecialchars($character['webhook'])?>">
    </section>
  </section>

  <hr>

  <section>
    <h2>キャラクター削除</h2>

    <section class="form">
      <div class="form-description">キャラクター削除を行う場合は、以下の入力欄に「DELETE」と入力してください。</div>
      <input id="input-delete-check" name="delete_check" class="form-input" type="text">
    </section>

    <div id="error-message-area"></div>

    <div class="button-wrapper">
      <button class="button">送信</button>
    </div>
  </section>
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
    var inputWebhook = $('#input-webhook').val();
    var inputDeleteCheck = $('#input-delete-check').val();

    // 許容可能なウェブフックURLの先頭部分
    var acceptableWebhookURLs = <?=
      json_encode($GAME_CONFIG['WEBHOOK_ACCEPTABLE_PREFIXES'])
    ?>;

    // 入力値検証
    // ウェブフックURLが入力されており、形式が不正（URLの先頭部分が不適）な場合エラーメッセージを表示して処理を中断
    if (inputWebhook) {
      var checkPassed = false;

      for (var i = 0; i < acceptableWebhookURLs.length; i++) {
        if (inputWebhook.indexOf(acceptableWebhookURLs[i]) === 0) {
          checkPassed = true;
          break;
        }
      }

      if (!checkPassed) {
        showErrorMessage('ウェブフックURLの形式が不正です');
        return false;
      }
    }

    waitingResponse = true; // レスポンス待ち状態をONに
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>