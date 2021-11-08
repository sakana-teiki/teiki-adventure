<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  $statement = $GAME_PDO->prepare("
    SHOW TABLES;
  ");

  $result = $statement->execute();

  if (!$result) { // 失敗した場合は500(Internal Server Error)を返して処理を中断
    responseError(500);
  }

  $tables = $statement->fetchAll();

  // テーブルが作成されている状態で初期化ページを表示している/初期化しようとしている場合は管理者権限が必要
  // （逆に言うと、テーブルがまっさらな状態では管理者権限を必要としない）
  // （テーブルがまっさらな状態だと管理者権限を持ったユーザーも存在しないため）
  if ($tables) {
    require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';
  }

  // POSTの場合
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    // 以下の条件のうちいずれかを満たせば403(Forbidden)を返し処理を中断
    if (
      !isset($_POST['initialize_key']) // 受け取ったデータに初期化キーがない
    ) {
      responseError(400);
    }
    
    // クライアント側のハッシュ化と同様のハッシュ化処理
    $initializeKey = $GAME_CONFIG['INITIALIZE_KEY'].$GAME_CONFIG['CLIENT_HASH_SALT']; // ソルティング
    for ($i = 0; $i < $GAME_CONFIG['CLIENT_HASH_STRETCH']; $i++) { // ストレッチング
      $initializeKey = hash('sha256', $initializeKey);
    }
  
    // ハッシュ化された初期化キーがサーバー側のものと一致しなければ403(Forbidden)を返し処理を中断
    if ($_POST['initialize_key'] !== $initializeKey) {
      responseError(403);
    }

    // 問題なければ初期化を行う
    $ACTION_INITIALIZE['skip_confirm'] = true;
    require GETENV('GAME_ROOT').'/actions/initialize.php';

    // セッションの削除
    $_SESSION = array();

    // 処理を終了
    exit;
  }

  $PAGE_SETTING['TITLE'] = 'データ初期化';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>データ初期化</h1>

<section>
  <div id="error-message-area"></div>

  <section class="form">
    <div class="form-title">初期化キー</div>
    <div class="form-description" style="color: red; font-weight: bold;">
      初期化キーを入力してデータ初期化を押すと、DB内の全てのデータを初期化します。<br>
      初期化したデータは戻せないため注意してください。<br>
      初期化を行ったクライアント以外のセッションデータは残留しているため、必要な場合はセッションの削除も行ってください。
    </div>
    <input id="input-initialize-key" class="form-input" type="password" placeholder="初期化キー">
  </section>
  
  <div class="button-wrapper">
    <button id="signin-button" class="button">データ初期化</button>
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
    var inputInitializeKey = $('#input-initialize-key').val();
  
    // 初期化キーが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputInitializeKey) {
      showErrorMessage('初期化キーが入力されていません');
      return;
    }

    // 初期化キーのハッシュ化
    var hashedInitializeKey = inputInitializeKey + hashSalt; // ソルティング
    for (var i = 0; i < hashStretch; i++) { // ストレッチング
      var shaObj = new jsSHA("SHA-256", "TEXT");
      shaObj.update(hashedInitializeKey);
      hashedInitializeKey = shaObj.getHash("HEX");
    }

    // 送信
    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return;
    }

    waitingResponse = true; // レスポンス待ち状態をONに
    $.post(location.href, { // このページのURLにPOST送信
<?php if ($tables) { ?>
      csrf_token: '<?=$_SESSION['token']?>',
<?php } ?>
      initialize_key: hashedInitializeKey
    }).done(function() {
      location.href = '<?=$GAME_CONFIG['TOP_URI']?>'; // トップにリダイレクト
    }).fail(function() { 
      showErrorMessage('初期化できませんでした'); // エラーが発生した場合エラーメッセージを表示
    }).always(function() {
      waitingResponse = false;  // 接続終了後はレスポンス待ち状態を解除
    });
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>