<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';
  
  $PAGE_SETTING['TITLE'] = 'コントロールパネル';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>コントロールパネル</h1>

<section>
  <h2>ゲーム状態変更</h2>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/maintenance">メンテナンス切り替え</a><br>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/ap/status">自動AP配布設定切り替え</a><br>
  
  <h2>更新関連</h2>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/ap/distribute">AP配布</a><br>

  <h2>通知関連</h2>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/announcement">お知らせ発行</a><br>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/notification">通知送信</a><br>

  <h2>キャラクターデータ変更</h2>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/character/icon-limit">アイコン上限付与</a><br>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/character/password">パスワード再発行</a><br>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/character/delete">指定キャラクター削除</a><br>

  <h2>マスタデータ</h2>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/master/export">エクスポート</a><br>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/master/import">インポート</a><br>

  <h2>データ初期化</h2>
  <a href="<?=$GAME_CONFIG['URI']?>control-panel/initialize">データ初期化</a><br>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>