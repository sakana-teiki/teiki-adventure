<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('password', ['non-empty'])            ||
      !validatePOST('eno',      ['non-empty', 'integer'])
    ) {
      responseError(400);
    }

    // 検索処理
    // 削除フラグが立っておらずENoが指定のキャラクターを検索
    $statement = $GAME_PDO->prepare("
      SELECT
        `ENo`,
        `password`,
        `token`,
        `administrator`
      FROM
        `characters`
      WHERE
        `ENo`     = :ENo  AND
        `deleted` = false;
    ");

    $statement->bindParam(':ENo', $_POST['eno']);

    $result = $statement->execute();

    if (!$result) {
      // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      responseError(500); 
    }

    $data = $statement->fetch(); // 実行結果を取得

    if (!$data) {
      // 結果が見つからない場合は404(Not Found)を返し処理を中断
      responseError(404);
    }

    if (!password_verify($_POST['password'], $data['password'])) {
      // パスワードの照合を行い合致しない場合は403(Forbidden)を返し処理を中断
      responseError(403);
    }
    
    session_regenerate_id(true); // セッションIDを再生成（セッション固定攻撃対策）

    $_SESSION['ENo']   = $data['ENo'];                   // セッションにENoを設定
    $_SESSION['token'] = $data['token'];                 // セッションにCSRFトークンを設定
    $_SESSION['administrator'] = $data['administrator']; // セッションに管理者ステータスを設定

    header('Location:'.$GAME_CONFIG['URI'].'home', true, 302); // ホームにリダイレクト
    exit;
  }

  $PAGE_SETTING['TITLE'] = 'ログイン';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<?php
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // GETリクエスト時の処理
?>

<section>
  <h2>ログイン</h2>

  <section class="form">
    <div class="form-title">ENo</div>
    <input id="input-eno" class="form-input" type="text" placeholder="ENo">
  </section>

  <section class="form">
    <div class="form-title">パスワード</div>
    <input id="input-password" class="form-input" type="password" placeholder="パスワード">
  </section>

  <div id="error-message-area"></div>
  
  <form id="form" method="post">
    <input id="input-hidden-eno" type="hidden" name="eno">
    <input id="input-hidden-password" type="hidden" name="password">

    <div class="button-wrapper">
      <button class="button">ログイン</button>
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
    var inputENo      = $('#input-eno').val();
    var inputPassword = $('#input-password').val();

    // 入力値検証
    // フルネームが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputENo) {
      showErrorMessage('ENoが入力されていません');
      return false;
    }
    // ENoの入力形式が正しくない場合エラーメッセージを表示して処理を中断
    // (数として解釈不能、整数でない)
    if (Number(inputENo) == NaN || !Number.isInteger(Number(inputENo))) {
      showErrorMessage('ENoの入力形式が正しくありません');
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
    $('#input-hidden-eno').val(inputENo);
    $('#input-hidden-password').val(hashedPassword);

    waitingResponse = true; // レスポンス待ち状態をONに
  });
</script>

<?php
  }
?>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>