<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    // 以下の条件のうちいずれかを満たせば400(Bad Request)を返し処理を中断
    if (
      !isset($_POST['name'])     || // 受け取ったデータにフルネームがない
      !isset($_POST['nickname']) || // 受け取ったデータに短縮名がない
      !isset($_POST['password']) || // 受け取ったデータにパスワードがない
      $_POST['name']     == ''   || // 受け取ったフルネームが空文字列
      $_POST['nickname'] == ''   || // 受け取った短縮名が空文字列
      $_POST['password'] == ''   || // 受け取ったパスワードが空文字列
      mb_strlen($_POST['name'])     > $GAME_CONFIG['NAME_MAX_LENGTH']  || // 受け取ったフルネームが長すぎる
      mb_strlen($_POST['nickname']) > $GAME_CONFIG['NICKNAME_MAX_LENGTH'] // 受け取った短縮名が長すぎる
    ) {
      http_response_code(400);
      exit;
    }

    // パスワードのサーバー側ハッシュ化
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // CSRFトークンの生成
    $token = bin2hex(openssl_random_pseudo_bytes($GAME_CONFIG['CSRF_TOKEN_LENGTH']));

    // DB登録処理
    $statement = $GAME_PDO->prepare('
      INSERT INTO `characters` (
        `name`, `nickname`, `password`, `token`
      ) VALUES (
        :name, :nickname, :password, :token
      );
    ');

    $statement->bindParam(':name',     $_POST['name']);
    $statement->bindParam(':nickname', $_POST['nickname']);
    $statement->bindParam(':password', $password);
    $statement->bindParam(':token',    $token);

    $result = $statement->execute();

    if (!$result) {
      http_response_code(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返し処理を中断
      exit;
    }

    $ENo = intval($GAME_PDO->lastInsertId()); // ENoを取得
    session_regenerate_id(true); // セッションIDを再生成（セッション固定攻撃対策）

    $_SESSION['ENo']   = $ENo;   // セッションにENoを設定
    $_SESSION['token'] = $token; // セッションにCSRFトークンを設定

    http_response_code(200); // ここまで全てOKなら200を返して処理を終了
    exit;
  }

  $PAGE_SETTING['TITLE'] = '新規登録';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

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

  <div id="error-message-area"></div>

  <section class="form">
    <div class="form-title">キャラクターのフルネーム（<?=$GAME_CONFIG['NAME_MAX_LENGTH']?>文字まで）</div>
    <div class="form-description">
      キャラクターのフルネームを入力します。後からでも変更可能です。
    </div>
    <input id="input-name" class="form-input" type="text" placeholder="フルネーム">
  </section>

  <section class="form">
    <div class="form-title">キャラクターの短縮名（<?=$GAME_CONFIG['NICKNAME_MAX_LENGTH']?>文字まで）</div>
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
    if (inputName.length > <?=$GAME_CONFIG['NAME_MAX_LENGTH']?>) {
      showErrorMessage('フルネームが長すぎます');
      return;
    }

    // 短縮名が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputNickname) {
      showErrorMessage('短縮名が入力されていません');
      return;
    }
    // 短縮名が長すぎる場合エラーメッセージを表示して処理を中断
    if (inputNickname.length > <?=$GAME_CONFIG['NICKNAME_MAX_LENGTH']?>) {
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