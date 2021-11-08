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
      responseError(400);
    }

    // 入力値検証2
    // boardの指定がcommunityでもtradeでもbugでもなければ400(Bad Request)を返し処理を中断
    if (
      $_POST['board'] !== 'community' &&
      $_POST['board'] !== 'trade'     &&
      $_POST['board'] !== 'bug'
    ) {
      responseError(400);
    }

    // パスワードのサーバー側ハッシュ化
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // IPから発言IDを生成
    $identifier = substr(base64_encode(hash_hmac("sha1", $_SERVER["REMOTE_ADDR"], $GAME_CONFIG['IDENTIFIER_SECRET'])), 0, $GAME_CONFIG['IDENTIFIER_LENGTH']);

    $GAME_PDO->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

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

    $statement->bindValue(':title',         $_POST['title']);
    $statement->bindValue(':name',          $_POST['name']);
    $statement->bindValue(':identifier',    $identifier);
    $statement->bindValue(':message',       $_POST['message']);
    $statement->bindValue(':secret',        $_POST['secret']);
    $statement->bindValue(':password',      $password);
    $statement->bindValue(':administrator', $GAME_LOGGEDIN_AS_ADMINISTRATOR, PDO::PARAM_BOOL);
    $statement->bindValue(':board',         $_POST['board']);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    $lastInsertId = intval($GAME_PDO->lastInsertId()); // 登録されたidを取得

    header('Location:'.$GAME_CONFIG['URI'].'thread?id='.$lastInsertId, true, 302); // 建てられたスレッドにリダイレクト
    exit;
  }

  // URLパラメータにboardの指定がなければ404(Not Found)を返して処理を中断
  if (!isset($_GET['board'])) {
    responseError(404);
  }

  switch($_GET['board']) {
    case 'community':
      $boardTitle = '交流'; // URLパラメータのboardの指定がcommunityの場合掲示板名を「交流」に
      break;
    case 'trade':
      $boardTitle = '取引'; // URLパラメータのboardの指定がtradeの場合掲示板名を「取引」に
      break;
    case 'bug':
      $boardTitle = '不具合'; // URLパラメータのboardの指定がbugの場合掲示板名を「不具合」に
      break;
    default:
      responseError(404); // boardの指定がそれ以外の場合404(Not Found)を返して処理を中断
  }

  // 現在のページ
  // pageの指定があればその値-1、指定がなければ0
  // インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $page = isset($_GET['page']) ? intval($_GET['page']) -1 : 0;

  // ページが負なら400(Bad Request)を返して処理を中断
  if ($page < 0) {
    responseError(400);
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
    responseError(500);
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
<style>

.thread-list {
  border-collapse: collapse;
  margin: 0 auto;
  font-size: 15px;
}

.thread-list th {
  background-color: #444;
  border: 1px solid #F8F8F8;
  color: #EEE;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
}

.thread-list td {
  border: 1px solid #F8F8F8;
}

.thread-list th:nth-child(1), .thread-list td:nth-child(1) {
  text-align: center;
  width: 50px;
}

.thread-list th:nth-child(2), .thread-list td:nth-child(2) {
  text-align: center;
  width: 30px;
}

.thread-list th:nth-child(3), .thread-list td:nth-child(3) {
  width: 300px;
  max-width: 300px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.thread-list th:nth-child(4), .thread-list td:nth-child(4) {
  width: 100px;
  max-width: 100px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.thread-list th:nth-child(5), .thread-list td:nth-child(5) {
  text-align: center;
  width: 50px;
}

.thread-list th:nth-child(6), .thread-list td:nth-child(6) {
  text-align: center;
  width: 200px;
}

.thread-list a {
  text-decoration: none;
  font-weight: bold;
  color: #444;
}

.flag-administrator {
  color: #c2193e;
  font-weight: bold;
}

</style>
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

  <form id="form" method="post">
    <input type="hidden" name="board" value="<?=$_GET['board']?>">
    <input id="input-hidden-title" type="hidden" name="title">
    <input id="input-hidden-name" type="hidden" name="name">
    <input id="input-hidden-message" type="hidden" name="message">
    <input id="input-hidden-secret" type="hidden" name="secret">
    <input id="input-hidden-password" type="hidden" name="password">

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

  $('#form').submit(function(){
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
      return false;
    }
    // タイトルが長すぎる場合エラーメッセージを表示して処理を中断
    if (inputTitle.length > <?=$GAME_CONFIG['THREAD_TITLE_MAX_LENGTH']?>) {
      showErrorMessage('タイトルが長すぎます');
      return false;
    }

    // 名前が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputName) {
      showErrorMessage('名前が入力されていません');
      return false;
    }
    // 名前が長すぎる場合エラーメッセージを表示して処理を中断
    if (inputName.length > <?=$GAME_CONFIG['THREAD_NAME_MAX_LENGTH']?>) {
      showErrorMessage('名前が長すぎます');
      return false;
    }

    // 本文が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputMessage) {
      showErrorMessage('本文が入力されていません');
      return false;
    }
    // 本文が長すぎる場合エラーメッセージを表示して処理を中断
    if (inputMessage.length > <?=$GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH']?>) {
      showErrorMessage('本文が長すぎます');
      return false;
    }

    // 編集パスワードが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputPassword) {
      showErrorMessage('編集パスワードが入力されていません');
      return false;
    }

    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // パスワードのハッシュ化
    var hashedPassword = inputPassword + hashSalt; // ソルティング
    for (var i = 0; i < hashStretch; i++) { // ストレッチング
      var shaObj = new jsSHA("SHA-256", "TEXT");
      shaObj.update(hashedPassword);
      hashedPassword = shaObj.getHash("HEX");
    }

    // 送信
    $('#input-hidden-title').val(inputTitle);
    $('#input-hidden-name').val(inputName);
    $('#input-hidden-message').val(inputMessage);
    $('#input-hidden-secret').val(inputSecret);
    $('#input-hidden-password').val(hashedPassword);

    waitingResponse = true; // レスポンス待ち状態をONに
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>