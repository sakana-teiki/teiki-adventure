<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (!validatePOST('maintenance', ['non-empty', 'boolean'])) {
      responseError(400);
    }

    // メンテナンス状態の切り替え
    $statement = $GAME_PDO->prepare("
      UPDATE
        `game_status`
      SET
        `maintenance` = :maintenance;
    ");

    $statement->bindValue(':maintenance', $_POST['maintenance'] == 'true', PDO::PARAM_BOOL);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // 完了したらリロードさせる
    header("Location: ".$GAME_CONFIG['URI'].'control-panel/maintenance');
  }

  $PAGE_SETTING['TITLE'] = 'メンテナンス切り替え';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>メンテナンス切り替え</h1>

<section>
  <h2>状態指定</h2>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

    <section class="form">
      <div class="form-title">メンテナンス状態</div>
      <select class="form-input" name="maintenance">
        <option value="false">非メンテナンス中</option>
        <option value="true">メンテナンス中</option>
      </select>
    </section>

    <div class="button-wrapper">
      <button class="button" type="submit">変更</button>
    </div>
  </form>
</section>

<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）

  $('#form').submit(function(){
    // レスポンス待ちの場合アラートを表示して送信を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 上記のどれにも当てはまらない場合送信が行われるためレスポンス待ちをONに
    waitingResponse = true;
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>