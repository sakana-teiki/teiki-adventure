<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('password', ['non-empty']) ||
      !validatePOST('name',     ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['CHARACTER_NAME_MAX_LENGTH'])     ||
      !validatePOST('nickname', ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH'])
    ) {
      http_response_code(400);
      exit;
    }

    // パスワードのサーバー側ハッシュ化
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // CSRFトークンの生成
    $token = bin2hex(openssl_random_pseudo_bytes($GAME_CONFIG['CSRF_TOKEN_LENGTH']));

    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // キャラクター情報の登録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `characters` (
        `name`,
        `nickname`,
        `password`,
        `token`,
        `summary`,
        `profile`,
        `webhook`
      ) VALUES (
        :name,
        :nickname,
        :password,
        :token,
        '',
        '',
        ''
      );
    ");

    $statement->bindParam(':name',     $_POST['name']);
    $statement->bindParam(':nickname', $_POST['nickname']);
    $statement->bindParam(':password', $password);
    $statement->bindParam(':token',    $token);

    $result = $statement->execute();

    if (!$result) {
      http_response_code(500); // 失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
      $GAME_PDO->rollBack();
      exit;
    }

    $lastInsertId = intval($GAME_PDO->lastInsertId()); // 登録されたidを取得

    // ENo情報の登録（登録されたキャラクターのうち、管理者アカウントを除いた指定のid以下のキャラクターが何キャラクター存在しているか？の数をENoとする。）
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `ENo` = (
          SELECT
            COUNT(*)
          FROM
            `characters`
          WHERE
            `administrator` = false AND `id` <= :id
        )
      WHERE
        `id` = :id;
    ");

    $statement->bindParam(':id', $lastInsertId);

    $result = $statement->execute();

    if (!$result) {
      http_response_code(500); // 失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
      $GAME_PDO->rollBack();
      exit;
    }

    // 登録されたENoの取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `ENo`
      FROM
        `characters`
      WHERE
        `id` = :id;
    ");

    $statement->bindParam(':id', $lastInsertId);

    $result    = $statement->execute();
    $character = $statement->fetch();

    if (!$result || !$character) {
      http_response_code(500); // 失敗あるいは結果が存在しない場合は500(Internal Server Error)を返してロールバックし、処理を中断
      $GAME_PDO->rollBack();
      exit;
    }

    // ここまで全て成功した場合はコミット
    $GAME_PDO->commit();

    session_regenerate_id(true); // セッションIDを再生成（セッション固定攻撃対策）

    $_SESSION['ENo']           = $character['ENo']; // セッションにENoを設定
    $_SESSION['token']         = $token;            // セッションにCSRFトークンを設定
    $_SESSION['administrator'] = false;             // セッションに管理者ステータスを設定

    http_response_code(200); // ここまで全てOKなら200を返して処理を終了
    exit;
  }

  $PAGE_SETTING['TITLE'] = '新規登録';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<?php
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // GETリクエスト時の処理
?>

<section>
  <h2>利用規約</h2>
  <ol id="terms-list">
    <li>登録は1人1キャラクターのみ</li>
    <li>登録はオリジナルキャラクターのみ</li>
    <li>不適切な画像の設定や投稿などを行わないこと</li>
  </ol>
</section>
<section>
  <h2>新規登録</h2>

  <section class="form">
    <div class="form-title">キャラクターのフルネーム（<?=$GAME_CONFIG['CHARACTER_NAME_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      キャラクターのフルネームを入力します。後からでも変更可能です。
    </div>
    <input id="input-name" class="form-input" type="text" placeholder="フルネーム">
  </section>

  <section class="form">
    <div class="form-title">キャラクターの短縮名（<?=$GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      キャラクターの短縮名を入力します。後からでも変更可能です。
    </div>
    <input id="input-nickname" class="form-input" type="text" placeholder="短縮名">
  </section>

  <section class="form">
    <div class="form-title">パスワード（<?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>文字以上）</div>
    <div class="form-description">
      ログインに使用するパスワードを入力します。
    </div>
    <input id="input-password" class="form-input" type="password" placeholder="パスワード">
  </section>

  <section class="form">
    <div class="form-title">パスワード再入力</div>
    <div class="form-description">
      再度同じパスワードを入力します。
    </div>
    <input id="input-confirm" class="form-input" type="password" placeholder="再入力">
  </section>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button id="signup-button" class="button">登録</button>
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

　$('#signup-button').on('click', function() {
    // 各種の値を取得
    var inputName     = $('#input-name').val();
    var inputNickname = $('#input-nickname').val();
    var inputPassword = $('#input-password').val();
    var inputConfirm  = $('#input-confirm').val();

    // 入力値検証
    // フルネームが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputName) {
      showErrorMessage('フルネームが入力されていません');
      return;
    }
    // フルネームが長すぎる場合エラーメッセージを表示して処理を中断
    if (inputName.length > <?=$GAME_CONFIG['CHARACTER_NAME_MAX_LENGTH']?>) {
      showErrorMessage('フルネームが長すぎます');
      return;
    }

    // 短縮名が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputNickname) {
      showErrorMessage('短縮名が入力されていません');
      return;
    }
    // 短縮名が長すぎる場合エラーメッセージを表示して処理を中断
    if (inputNickname.length > <?=$GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH']?>) {
      showErrorMessage('短縮名が長すぎます');
      return;
    }

    // パスワードが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputPassword) {
      showErrorMessage('パスワードが入力されていません');
      return;
    }
    // パスワードが短すぎる場合エラーメッセージを表示して処理を中断
    if (inputPassword.length < <?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>) {
      showErrorMessage('パスワードが短すぎます');
      return;
    }

    // パスワード再入力が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputConfirm) {
      showErrorMessage('パスワード再入力が入力されていません');
      return;
    }
    // 再入力したパスワードが一致しない場合エラーメッセージを表示して処理を中断
    if (inputPassword != inputConfirm) {
      showErrorMessage('パスワードと再入力の内容が一致しません');
      return;
    }

    // パスワードのハッシュ化
    var hashedPassword = inputPassword + hashSalt; // ソルティング
    for (var i = 0; i < hashStretch; i++) { // ストレッチング
      var shaObj = new jsSHA("SHA-256", "TEXT");
      shaObj.update(hashedPassword);
      hashedPassword = shaObj.getHash("HEX");
    }

    // 送信
    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return;
    }

    waitingResponse = true; // レスポンス待ち状態をONに
    $.post(location.href, { // このページのURLにPOST送信
      name:     inputName,
      nickname: inputNickname,
      password: hashedPassword
    }).done(function() {
      location.href = '<?=$GAME_CONFIG['HOME_URI']?>'; // ホームにリダイレクト
    }).fail(function() { 
      showErrorMessage('登録処理中にエラーが発生しました'); // エラーが発生した場合エラーメッセージを表示
    }).always(function() {
      waitingResponse = false;  // 接続終了後はレスポンス待ち状態を解除
    });
  });
</script>

<?php
  } 
?>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>