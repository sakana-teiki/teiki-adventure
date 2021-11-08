<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';

  // POSTの場合
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ACTION_EXPORT_MASTER['skip_confirm'] = true;
    require GETENV('GAME_ROOT').'/actions/import_master.php';

    // 完了したらリロードさせる
    header("Location: ".$GAME_CONFIG['URI'].'control-panel/master/import');
  }

  $PAGE_SETTING['TITLE'] = 'マスタデータインポート';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>マスタデータインポート</h1>

<section>
  <h2>インポート</h2>

  <p>
    インポートボタンを押すとmasters/内のデータをテーブル上に読み込みます。<br>
    外部キー制約の対象となるカラムを含むテーブルについては整合性上の理由から要素の削除については反映されないため注意してください。該当のテーブルの要素の削除はデータベース上から操作する必要があります。
  </p>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

    <div class="button-wrapper">
      <button class="button" type="submit">インポート</button>
    </div>
  </form>
</section>

<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）

  $('#form').submit(function() {
    // レスポンス待ちの場合アラートを表示して送信を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // レスポンス待ちをONに
    waitingResponse = true;
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>