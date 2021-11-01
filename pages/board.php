<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('board',    []) ||
      !validatePOST('secret',   []) ||
      !validatePOST('password', ['non-empty']) ||
      !validatePOST('title',    ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_TITLE_MAX_LENGTH'])   ||
      !validatePOST('name',     ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_NAME_MAX_LENGTH'])    ||
      !validatePOST('message',  ['non-empty'               , 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH'])
    ) {
      http_response_code(400);
      exit;
    }

    // 入力値検証2
    // boardの指定がcommunityでもbugでもなければ400(Bad Request)を返し処理を中断
    if ($_POST['board'] !== 'community' && $_POST['board'] !== 'bug') {
      http_response_code(400);
      exit;
    }

    // パスワードのサーバー側ハッシュ化
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // IPから発言IDを生成
    $identifier = substr(base64_encode(hash_hmac("sha1", $_SERVER["REMOTE_ADDR"], $GAME_CONFIG['IDENTIFIER_SECRET'])), 0, $GAME_CONFIG['IDENTIFIER_LENGTH']);

    // スレッドの登録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `threads` (
        `title`,
        `name`,
        `identifier`,
        `message`,
        `secret`,
        `password`,
        `administrator`,
        `board`
      ) VALUES (
        :title,
        :name,
        :identifier,
        :message,
        :secret,
        :password,
        :administrator,
        :board
      );
    ");

    $statement->bindParam(':title',         $_POST['title']);
    $statement->bindParam(':name',          $_POST['name']);
    $statement->bindParam(':identifier',    $identifier);
    $statement->bindParam(':message',       $_POST['message']);
    $statement->bindParam(':secret',        $_POST['secret']);
    $statement->bindParam(':password',      $password);
    $statement->bindParam(':administrator', $GAME_LOGGEDIN_AS_ADMINISTRATOR);
    $statement->bindParam(':board',         $_POST['board']);

    $result = $statement->execute();

    if (!$result) {
      http_response_code(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
      exit;
    }

    $lastInsertId = intval($GAME_PDO->lastInsertId()); // 登録されたidを取得

    echo json_encode(array( // 作成されたスレッドのIDをJSON形式で返却して処理を終了
      'id' => $lastInsertId
    ));
    exit;
  }

  // URLパラメータにboardの指定がなければ404(Not Found)を返して処理を中断
  if (!isset($_GET['board'])) {
    http_response_code(404);
    exit;
  }

  switch($_GET['board']) {
    case 'community':
      $boardTitle = '交流'; // URLパラメータのboardの指定がcommunityの場合掲示板名を「交流」に
      break;
    case 'bug':
      $boardTitle = '不具合'; // URLパラメータのboardの指定がbugの場合掲示板名を「不具合」に
      break;
    default:
      http_response_code(404); // boardの指定がそれ以外の場合404(Not Found)を返して処理を中断
      exit;
  }

  // 現在のページ
  // pageの指定があればその値-1、指定がなければ0
  // インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $page = isset($_GET['page']) ? intval($_GET['page']) -1 : 0;

  // ページが負なら400(Bad Request)を返して処理を中断
  if ($page < 0) {
    http_response_code(400); 
    exit;
  }

  // 現在のページのスレッドを取得
  // 削除フラグが立っておらずページに応じた範囲のスレッドを検索
  // デフォルトの設定ではページ0であれば0件飛ばして50件、ページ1であれば50件飛ばして50件、ページ2であれば100件飛ばして50件、ページnであればn×50件飛ばして50件を表示します。
  // ただし、次のページがあるかどうか検出するために1件余分に取得します。
  // 51件取得してみて51件取得できれば次のページあり、取得結果の数がそれ未満であれば次のページなし（最終ページ）として扱います。
  $statement = $GAME_PDO->prepare("
    SELECT
      `threads`.`id`,
      `threads`.`title`,
      `threads`.`name`,
      `threads`.`last_posted_at`,
      `threads`.`administrator`,
      `threads`.`state`,
      (
        SELECT
          COUNT(*)
        FROM
          `threads_responses`
        WHERE
          `threads_responses`.`thread` = `threads`.`id` AND
          `deleted` = false
      ) AS `responses`
    FROM
      `threads`
    WHERE
      `threads`.`board`  = :board    AND
      `threads`.`state` != 'deleted'
    ORDER BY
      `threads`.`last_posted_at` DESC
    LIMIT
      :offset, :number;
  ");

  $statement->bindParam(':board',  $_GET['board']);
  $statement->bindValue(':offset', $page * $GAME_CONFIG['THREADS_PER_PAGE'], PDO::PARAM_INT);
  $statement->bindValue(':number', $GAME_CONFIG['THREADS_PER_PAGE'] + 1,     PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  $threads = $statement->fetchAll();

  // 1件余分に取得できていれば次のページありとして余分な1件を切り捨て
  if (count($threads) == $GAME_CONFIG['THREADS_PER_PAGE'] + 1) {
    $existsNext = true;
    array_pop($threads);
  } else {
  // 取得件数が足りなければ次のページなしとする
    $existsNext = false;
  }

  // ステータス表示用
  $statusText = array(
    'open'    => '',
    'closed'  => '済',
    'deleted' => ''
  );

  $PAGE_SETTING['TITLE'] = $boardTitle.'掲示板';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1><?=$boardTitle?>掲示板</h1>

<section>
  <table class="thread-list">
    <thead>
      <tr>
        <th>ID</th>
        <th>済</th>
        <th>タイトル</th>
        <th>投稿者</th>
        <th>レス</th>
        <th>最終投稿</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($threads as $thread) { ?>
      <tr>
        <td>#<?=$thread['id']?></td>
        <td><?=$statusText[$thread['state']]?></td>
        <td><a href="<?=$GAME_CONFIG['URI']?>thread?id=<?=$thread['id']?>"><?=htmlspecialchars($thread['title'])?></a></td>
        <td><?=htmlspecialchars($thread['name'])?><?= $thread['administrator'] ? ' <span class="flag-administrator">[管理者]</span>' : ''?></td>
        <td><?=$thread['responses']?></td>
        <td><?=$thread['last_posted_at']?></td>
      </tr>
    <?php } ?>
    </tbody>
  </table>

  <section class="pagelinks next-prev-pagelinks">
    <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?board=<?=$_GET['board']?>&page=<?=$page+1-1?>">前のページ</a></div>
    <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
    <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?board=<?=$_GET['board']?>&page=<?=$page+1+1?>">次のページ</a></div>
  </section>
</section>

<hr>

<section>
  <h2>スレッド作成</h2>

  <section class="form">
    <div class="form-title">タイトル（<?=$GAME_CONFIG['THREAD_TITLE_MAX_LENGTH']?>文字まで）</div>
    <input id="input-title" name="title" class="form-input" type="name" placeholder="タイトル">
  </section>

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
</section>
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
    var inputTitle    = $('#input-title').val();
    var inputName     = $('#input-name').val();
    var inputMessage  = $('#input-message').val();
    var inputSecret   = $('#input-secret').val();
    var inputPassword = $('#input-password').val();

    // 入力値検証
    // タイトルが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputTitle) {
      showErrorMessage('タイトルが入力されていません');
      return;
    }
    // タイトルが長すぎる場合エラーメッセージを表示して処理を中断
    if (inputTitle.length > <?=$GAME_CONFIG['THREAD_TITLE_MAX_LENGTH']?>) {
      showErrorMessage('タイトルが長すぎます');
      return;
    }

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

    // パスワードのハッシュ化
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
      board:    '<?=$_GET['board']?>',
      title:    inputTitle,
      name:     inputName,
      message:  inputMessage,
      secret:   inputSecret,
      password: hashedPassword
    }).done(function(responseDataString) {
      var responseData = JSON.parse(responseDataString); // 結果がJSONで帰ってくるのでパース
      location.href = '<?=$GAME_CONFIG['URI']?>thread?id='+responseData.id; // リダイレクト
    }).fail(function() { 
      showErrorMessage('処理中にエラーが発生しました'); // エラーが発生した場合エラーメッセージを表示
    }).always(function() {
      waitingResponse = false;  // 接続終了後はレスポンス待ち状態を解除
    });
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>