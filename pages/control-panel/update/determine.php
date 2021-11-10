<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';

  // POSTの場合
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require GETENV('GAME_ROOT').'/actions/determine_update.php';
  }
  
  // ゲームステータスの取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `update_status`
    FROM
      `game_status`;
  ");

  $result     = $statement->execute();
  $gameStatus = $statement->fetch();

  if (!$result || !$gameStatus) {
    responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返して処理を中断
  }

  $PAGE_SETTING['TITLE'] = '更新確定';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>更新確定</h1>

<section>
<?php if ($gameStatus['update_status']) { ?>
  <p>
    すでに更新は確定されています。
  </p>
<?php } else { ?>
  <section class="form">
    <div class="form-title">更新確定</div>
    <div class="form-description">
      更新確定ボタンを押すと更新を確定します。
    </div>
  </section>
  
  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
  
    <div class="button-wrapper">
      <button class="button">更新確定</button>
    </div>
  </form>
<?php } ?>
</section>

<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）

  $('#form').submit(function() {
    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return;
    }

    waitingResponse = true; // レスポンス待ち状態をONに
  });
</script>
<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>