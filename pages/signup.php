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
      responseError(400);
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
      $GAME_PDO->rollBack(); // 失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
      responseError(500);
    }

    $lastInsertId = intval($GAME_PDO->lastInsertId()); // 登録されたidを取得

    // ENo情報の登録（登録されたキャラクターのうち、管理者アカウントを除いた指定のid以下のキャラクターが何キャラクター存在しているか？の数をENoとする。）
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `ENo` = (
          SELECT 
           *
          FROM (
            SELECT
              COUNT(*)
            FROM
              `characters`
            WHERE
              `administrator` = false AND `id` <= :id
          ) `c`
        )
      WHERE
        `id` = :id;
    ");

    $statement->bindParam(':id', $lastInsertId);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack(); // 失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
      responseError(500);
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
      $GAME_PDO->rollBack(); // 失敗あるいは結果が存在しない場合は500(Internal Server Error)を返してロールバックし、処理を中断
      responseError(500);
    }

    // ここまで全て成功した場合はコミット
    $GAME_PDO->commit();

    session_regenerate_id(true); // セッションIDを再生成（セッション固定攻撃対策）

    $_SESSION['ENo']           = $character['ENo']; // セッションにENoを設定
    $_SESSION['token']         = $token;            // セッションにCSRFトークンを設定
    $_SESSION['administrator'] = false;             // セッションに管理者ステータスを設定

    header('Location:'.$GAME_CONFIG['URI'].'home', true, 302); // ホームにリダイレクト
    exit;
  }

  $PAGE_SETTING['TITLE'] = '新規登録';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

#terms-list {
  margin: 20px;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

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

  <form id="form" method="post">
    <input id="input-hidden-name" type="hidden" name="name">
    <input id="input-hidden-nickname" type="hidden" name="nickname">
    <input id="input-hidden-password" type="hidden" name="password">

    <div class="button-wrapper">
      <button class="button">登録</button>
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
    var inputName     = $('#input-name').val();
    var inputNickname = $('#input-nickname').val();
    var inputPassword = $('#input-password').val();
    var inputConfirm  = $('#input-confirm').val();

    // 入力値検証
    // フルネームが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputName) {
      showErrorMessage('フルネームが入力されていません');
      return false;
    }
    // フルネームが長すぎる場合エラーメッセージを表示して処理を中断
    if (inputName.length > <?=$GAME_CONFIG['CHARACTER_NAME_MAX_LENGTH']?>) {
      showErrorMessage('フルネームが長すぎます');
      return false;
    }

    // 短縮名が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputNickname) {
      showErrorMessage('短縮名が入力されていません');
      return false;
    }
    // 短縮名が長すぎる場合エラーメッセージを表示して処理を中断
    if (inputNickname.length > <?=$GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH']?>) {
      showErrorMessage('短縮名が長すぎます');
      return false;
    }

    // パスワードが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputPassword) {
      showErrorMessage('パスワードが入力されていません');
      return false;
    }
    // パスワードが短すぎる場合エラーメッセージを表示して処理を中断
    if (inputPassword.length < <?=$GAME_CONFIG['PASSWORD_MIN_LENGTH']?>) {
      showErrorMessage('パスワードが短すぎます');
      return false;
    }

    // パスワード再入力が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputConfirm) {
      showErrorMessage('パスワード再入力が入力されていません');
      return false;
    }
    // 再入力したパスワードが一致しない場合エラーメッセージを表示して処理を中断
    if (inputPassword != inputConfirm) {
      showErrorMessage('パスワードと再入力の内容が一致しません');
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
    $('#input-hidden-name').val(inputName);
    $('#input-hidden-nickname').val(inputNickname);
    $('#input-hidden-password').val(hashedPassword);

    waitingResponse = true; // レスポンス待ち状態をONに
  });
</script>
<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>