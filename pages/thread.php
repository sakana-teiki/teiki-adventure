<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';
  require_once GETENV('GAME_ROOT').'/utils/decoration.php';
  
  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('secret',   []) ||
      !validatePOST('password', ['non-empty']) ||
      !validatePOST('thread',   ['non-empty', 'natural-number']) ||
      !validatePOST('name',     ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_NAME_MAX_LENGTH'])    ||
      !validatePOST('message',  ['non-empty'               , 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH'])
    ) {
      http_response_code(400); 
      exit;
    }

    // パスワードのサーバー側ハッシュ化
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // IPから発言IDを生成
    $identifier = substr(base64_encode(hash_hmac("sha1", $_SERVER["REMOTE_ADDR"], $GAME_CONFIG['IDENTIFIER_SECRET'])), 0, $GAME_CONFIG['IDENTIFIER_LENGTH']);

    // レスポンスの登録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `threads_responses` (
        `thread`,
        `name`,
        `identifier`,
        `message`,
        `secret`,
        `password`,
        `administrator`
      ) VALUES (
        :thread,
        :name,
        :identifier,
        :message,
        :secret,
        :password,
        :administrator
      );
    ");

    $statement->bindParam(':thread',        $_POST['thread']);
    $statement->bindParam(':name',          $_POST['name']);
    $statement->bindParam(':identifier',    $identifier);
    $statement->bindParam(':message',       $_POST['message']);
    $statement->bindParam(':secret',        $_POST['secret']);
    $statement->bindParam(':password',      $password);
    $statement->bindParam(':administrator', $GAME_LOGGEDIN_AS_ADMINISTRATOR);

    $result = $statement->execute();

    if (!$result) {
      http_response_code(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
      exit;
    }

    // スレッドの最終投稿の更新
    $statement = $GAME_PDO->prepare("
      UPDATE
        `threads`
      SET
        `last_posted_at` = CURRENT_TIMESTAMP
      WHERE
        `id` = :thread;
    ");

    $statement->bindParam(':thread', $_POST['thread']);

    $statement->execute();

    http_response_code(200); // ここまで終了したら200(OK)を返して処理を終了
    exit;
  }

  // idの内容を検証
  if (!validateGET('id', ['non-empty', 'natural-number'])) {
    http_response_code(400); 
    exit;
  }

  // スレッドの値を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `id`,
      `title`,
      `name`,
      `identifier`,
      `message`,
      `secret`,
      `created_at`,
      `updated_at`,
      `administrator`,
      `board`,
      `state`
    FROM
      `threads`
    WHERE
      `id` = :id AND `state` != 'deleted';
  ");

  $statement->bindParam(':id', $_GET['id']);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  $thread = $statement->fetch();

  if (!$thread) {
    // 実行結果の取得に失敗した場合は404(Not Found)を返し処理を中断
    http_response_code(404); 
    exit;
  }

  // スレッドのレスを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `id`,
      `name`,
      `identifier`,
      `message`,
      `secret`,
      `posted_at`,
      `updated_at`,
      `administrator`
    FROM
      `threads_responses`
    WHERE
      `thread` = :thread AND `deleted` = false;
  ");

  $statement->bindParam(':thread', $_GET['id']);

  $result    = $statement->execute();
  $responses = $statement->fetchAll();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  // ステータス表示用
  $statusText = array(
    'open'    => 'オープン',
    'closed'  => '済',
    'deleted' => ''
  );

  $PAGE_SETTING['TITLE'] = '#'.$thread['id'].' '.$thread['title'];

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<h1><?=htmlspecialchars('#'.$thread['id'].' '.$thread['title'])?></h1>

<section>
  <table class="thread-body">
    <tbody>
      <tr>
        <th>ステート</th>
        <td><?=$statusText[$thread['state']]?></td>
      </tr>
      <tr>
        <th>投稿者</th>
        <td><?=htmlspecialchars($thread['name'])?><?= $thread['administrator'] ? ' <span class="flag-administrator">[管理者]</span>' : ''?></td>
      </tr>
<?php if (!$thread['administrator']) { ?>
      <tr>
        <th>発言ID</th>
        <td><?=$thread['identifier']?></td>
      </tr>
<?php } ?>
      <tr>
        <th>投稿日時</th>
        <td><?=$thread['created_at']?></td>
      </tr>
      <?php if ($thread['created_at'] != $thread['updated_at']) { ?>
      <tr>
        <th>更新日時</th>
        <td><?=$thread['updated_at']?></td>
      </tr>
      <?php } ?>
      <tr>
        <td colspan="2" class="thread-body-message"><?=profileDecoration($thread['message'])?></td>
      </tr>
      <?php if ($GAME_LOGGEDIN_AS_ADMINISTRATOR && $thread['secret']) { ?>
      <tr>
        <th colspan="2">秘匿送信内容</th>
      </tr>
      <tr>
        <td colspan="2" class="thread-body-message"><?=profileDecoration($thread['secret'])?></td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
<?php if (!$thread['administrator'] || $GAME_LOGGEDIN_AS_ADMINISTRATOR) { ?>
  <div class="button-wrapper">
    <a href="<?=$GAME_CONFIG['URI']?>thread/edit?thread=<?=$thread['id']?>"><button class="button">編集</button></a>
  </div>
<?php } ?>
</section>

<hr>

<?php foreach ($responses as $response) { ?>
<section>
  <table class="thread-body">
    <tbody>
      <tr>
        <th>投稿者</th>
        <td><?=htmlspecialchars($response['name'])?><?= $response['administrator'] ? ' <span class="flag-administrator">[管理者]</span>' : ''?></td>
      </tr>
<?php if (!$response['administrator']) { ?>
      <tr>
        <th>発言ID</th>
        <td><?=$response['identifier']?></td>
      </tr>
<?php } ?>
      <tr>
        <th>投稿日時</th>
        <td><?=$response['posted_at']?></td>
      </tr>
      <?php if ($response['posted_at'] != $response['updated_at']) { ?>
      <tr>
        <th>更新日時</th>
        <td><?=$response['updated_at']?></td>
      </tr>
      <?php } ?>
      <tr>
        <td colspan="2" class="thread-body-message"><?=profileDecoration($response['message'])?></td>
      </tr>
      <?php if ($GAME_LOGGEDIN_AS_ADMINISTRATOR && $response['secret']) { ?>
      <tr>
        <th colspan="2">秘匿送信内容</th>
      </tr>
      <tr>
        <td colspan="2" class="thread-body-message"><?=profileDecoration($response['secret'])?></td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
<?php if (!$response['administrator'] || $GAME_LOGGEDIN_AS_ADMINISTRATOR) { ?>
  <div class="button-wrapper">
    <a href="<?=$GAME_CONFIG['URI']?>thread/edit?response=<?=$response['id']?>"><button class="button">編集</button></a>
  </div>
<?php } ?>
</section>

<hr>
<?php } ?>

<section>
  <h2>レス</h2>

  <section class="form">
    <div class="form-title">投稿者名（<?=$GAME_CONFIG['THREAD_NAME_MAX_LENGTH']?>文字まで）</div>
    <input id="input-name" name="name" class="form-input" type="name" placeholder="投稿者名">
  </section>

  <section class="form">
    <div class="form-title">本文（<?=$GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH']?>文字まで）</div>
    <textarea id="input-message" name="message" class="form-textarea" placeholder="本文"></textarea>
  </section>

  <section class="form">
    <div class="form-title">プレイ環境</div>
    <label>
      プレイ環境を送信する
      <input id="input-send-secret" type="checkbox">
    </label>
  </section>

  <section class="form" id="secret-area">
    <div class="form-description">
      送信されたプレイ環境は管理者のみが確認できます。<br>
      送信したくない項目が含まれている場合、該当の項目の記載を削除してください。
    </div>
    <textarea id="input-secret" name="secret" class="form-textarea" placeholder="プレイ環境"></textarea>
  </section>

  <section class="form">
    <div class="form-title">編集パスワード</div>
    <input id="input-password" name="password" class="form-input" type="password" placeholder="編集パスワード">
  </section>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button id="send-button" class="button">送信</button>
  </div>
</div>
<script src="<?=$GAME_CONFIG['URI']?>scripts/jssha-sha256.js"></script>
<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）
  var hashStretch = <?=$GAME_CONFIG['CLIENT_HASH_STRETCH']?>; // ハッシュ化の回数
  var hashSalt    = '<?=$GAME_CONFIG['CLIENT_HASH_SALT']?>';  // ハッシュ化の際のsalt

  // プレイ環境記入関連のDOMを取得
  var inputSendSecretDOM = $('#input-send-secret');
  var inputSecretDOM     = $('#input-secret');
  var secretAreaDOM      = $('#secret-area');

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

  // プレイ環境の記入項目を隠す
  secretAreaDOM.hide();

  // プレイ環境を送信するを選択した場合の処理
  inputSendSecretDOM.change(function() {
    if ($(this).prop('checked')) {
      // チェックを付けた際はsecretに各種プレイ環境を入力しプレイ環境の記入項目を表示する
      inputSecretDOM.val([
        '【プロトコル】' + location.protocol,
        '【ホスト名】' + location.hostname,
        '【ブラウザバージョン】' + navigator.appVersion,
        '【プラットフォーム】' + navigator.platform,
        '【スクリーン幅】' + screen.width,
        '【スクリーン高さ】' + screen.height,
        '【ビューポート幅】' + window.innerWidth,
        '【ビューポート高さ】' + window.innerHeight,
        '【デバイスピクセル比】' + window.devicePixelRatio,
        '【タッチ操作可能か】' + !!navigator.pointerEnabled
      ].join('\n'));
      secretAreaDOM.show();
    } else {
      // チェックを外した際はsecretの内容を削除しプレイ環境の記入項目を隠す
      inputSecretDOM.val('');
      secretAreaDOM.hide();
    }
  });

  $('#send-button').on('click', function() {
    // 各種の値を取得
    var inputName     = $('#input-name').val();
    var inputMessage  = $('#input-message').val();
    var inputSecret   = $('#input-secret').val();
    var inputPassword = $('#input-password').val();

    // 入力値検証
    // 名前が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputName) {
      showErrorMessage('名前が入力されていません');
      return;
    }
    // 名前が長すぎる場合エラーメッセージを表示して処理を中断
    if (inputName.length > <?=$GAME_CONFIG['THREAD_NAME_MAX_LENGTH']?>) {
      showErrorMessage('名前が長すぎます');
      return;
    }

    // 本文が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputMessage) {
      showErrorMessage('本文が入力されていません');
      return;
    }
    // 本文が長すぎる場合エラーメッセージを表示して処理を中断
    if (inputMessage.length > <?=$GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH']?>) {
      showErrorMessage('本文が長すぎます');
      return;
    }

    // 編集パスワードが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputPassword) {
      showErrorMessage('編集パスワードが入力されていません');
      return;
    }

    // 現在のパスワードのハッシュ化
    var hashedPassword = inputPassword + hashSalt; // ソルティング
    for (var i = 0; i < hashStretch; i++) { // ストレッチング
      var shaObj = new jsSHA("SHA-256", "TEXT");
      shaObj.update(hashedPassword);
      hashedPassword = shaObj.getHash("HEX");
    }

    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return;
    }

    waitingResponse = true; // レスポンス待ち状態をONに

    $.post(location.href, { // このページのURLにPOST送信
      thread:   '<?=$_GET['id']?>',
      name:     inputName,
      message:  inputMessage,
      secret:   inputSecret,
      password: hashedPassword
    }).done(function() {
      location.reload(); // 成功したらページをリロード
    }).fail(function() { 
      showErrorMessage('処理中にエラーが発生しました'); // エラーが発生した場合エラーメッセージを表示
    }).always(function() {
      waitingResponse = false;  // 接続終了後はレスポンス待ち状態を解除
    });
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>