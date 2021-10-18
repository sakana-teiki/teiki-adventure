<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    // 以下の条件のうちいずれかを満たせば400(Bad Request)を返し処理を中断
    if (
      !isset($_POST['eno'])      || // 受け取ったデータにENoがない
      !isset($_POST['password']) || // 受け取ったデータにパスワードがない
      $_POST['eno'] == ''        || // 受け取ったパスワードが空文字列
      !preg_match('/^([1-9][0-9]*)$/', $_POST['eno']) // ENoの内容が正の整数でない（判定には正規表現を使用）
    ) {
      http_response_code(400);
      exit;
    }

    // 検索処理
    // 削除フラグが立っておらずENoが指定のキャラクターを検索
    $statement = $GAME_PDO->prepare('
      SELECT
        `ENo`, `password`, `token`
      FROM
        `characters`
      WHERE
        `ENo`     = :ENo  AND
        `deleted` = false;
    ');

    $statement->bindParam(':ENo', $_POST['eno']);

    $result = $statement->execute();

    if (!$result) {
      // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      http_response_code(500); 
      exit;
    }

    $data = $statement->fetch(); // 実行結果を取得

    if (!$data) {
      // 結果が見つからない場合は404(Not Found)を返し処理を中断
      http_response_code(404);
      exit;
    }

    if (!password_verify($_POST['password'], $data['password'])) {
      // パスワードの照合を行い合致しない場合は403(Forbidden)を返し処理を中断
      http_response_code(403);
      exit;
    }
    
    session_regenerate_id(true); // セッションIDを再生成（セッション固定攻撃対策）

    $_SESSION['ENo']   = $data['ENo'];   // セッションにENoを設定
    $_SESSION['token'] = $data['token']; // セッションにCSRFトークンを設定

    http_response_code(200); // ここまで全てOKなら200を返して処理を終了
    exit;
  }

  $PAGE_SETTING['TITLE'] = 'ログイン';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<?php
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // GETリクエスト時の処理
?>

<section>
  <h2>ログイン</h2>

  <div id="error-message-area"></div>

  <section class="form">
    <div class="form-title">ENo</div>
    <input id="input-eno" class="form-input" type="text" placeholder="ENo">
  </section>

  <section class="form">
    <div class="form-title">パスワード</div>
    <input id="input-password" class="form-input" type="password" placeholder="パスワード">
  </section>
  
  <div class="button-wrapper">
    <button id="signin-button" class="button">ログイン</button>
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

　$('#signin-button').on('click', function() {
    // 各種の値を取得
    var inputENo      = $('#input-eno').val();
    var inputPassword = $('#input-password').val();

    // 入力値検証
    // フルネームが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputENo) {
      showErrorMessage('ENoが入力されていません');
      return;
    }
    // ENoの入力形式が正しくない場合エラーメッセージを表示して処理を中断
    // (数として解釈不能、整数でない、0以下)
    if (Number(inputENo) == NaN || !Number.isInteger(Number(inputENo)) || Number(inputENo) <= 0) {
      showErrorMessage('ENoの入力形式が正しくありません');
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
      eno:      Number(inputENo),
      password: hashedPassword
    }).done(function() {
      location.href = '<?=$GAME_CONFIG['HOME_URI']?>'; // ホームにリダイレクト
    }).fail(function() { 
      showErrorMessage('ログインに失敗しました'); // エラーが発生した場合エラーメッセージを表示
    }).always(function() {
      waitingResponse = false;  // 接続終了後はレスポンス待ち状態を解除
    });
  });
</script>

<?php
  }
?>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>