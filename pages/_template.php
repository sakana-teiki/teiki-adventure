<?php
  // ミドルウェアのロード部分
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  // ページの表示に必要なDBアクセスなどの表示前動作

  // ページの設定
  $PAGE_SETTING['TITLE'] = 'テンプレート';           // ページのタイトルを指定します。
  $PAGE_SETTING['DISABLE_TITLE_TEMPLATE'] = true;   // この項目にtrueを設定するとタイトルのテンプレート文字列を無効化します。

  // ページ上部の読み込み
  require GETENV('GAME_ROOT').'/components/header.php';
?>

ここにページの内容を記入

<?php require GETENV('GAME_ROOT').'/components/footer.php'; // ページ下部の読み込み ?>