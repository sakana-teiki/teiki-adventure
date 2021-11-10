<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';

  // POSTの場合
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require GETENV('GAME_ROOT').'/actions/update.php';
  }

  $PAGE_SETTING['TITLE'] = '更新確定';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>更新確定</h1>

<section>
  <section class="form">
    <div class="form-title">更新</div>
    <div class="form-description">
      更新ボタンを押すと更新あるいは再更新を行います。
    </div>
  </section>
  
  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
  
    <div class="button-wrapper">
      <button class="button">更新</button>
    </div>
  </form>
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