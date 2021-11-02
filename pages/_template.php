<?php
  // 内部処理部分

  // ミドルウェアのロード部分（取捨選択して読み込みます）
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  // ユーティリティ関数のロード部分（取捨選択して読み込みます）
  require_once GETENV('GAME_ROOT').'/utils/notification.php';
  require_once GETENV('GAME_ROOT').'/utils/validation.php';
  require_once GETENV('GAME_ROOT').'/utils/parser.php';
  require_once GETENV('GAME_ROOT').'/utils/decoration.php';

  // ページの表示に必要なDBアクセスなどの表示前動作

  // ページの設定
  $PAGE_SETTING['TITLE'] = 'テンプレート';           // ページのタイトルを指定します。
  $PAGE_SETTING['DISABLE_TITLE_TEMPLATE'] = true;   // この項目にtrueを設定するとタイトルのテンプレート文字列を無効化します。

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; // ページ上部の読み込み ?>
<style>
  /* ページ固有のスタイル適用部分 */
</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; // ページ上部末尾の読み込み ?>

<!-- 主にHTMLの記述部分 -->

ここにページの内容を記入

<?php require GETENV('GAME_ROOT').'/components/footer.php'; // ページ下部の読み込み ?>