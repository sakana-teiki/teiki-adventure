<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  
  $PAGE_SETTING['TITLE'] = 'Teiki Adventure';
  $PAGE_SETTING['DISABLE_TITLE_TEMPLATE'] = true;

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<h2>イントロダクション</h2>
<p>
  Teiki Adventureへようこそ。<br>
  このゲームはあなただけのオリジナルキャラクターを登録し、<br>
  世界を冒険したり他のキャラクターと交流したりしながら遊ぶゲームです。<br>
  定期・APゲーム制作のサンプルとして作られました。そのため実際に遊ぶことはできません。<br>
  <br>
  新規登録は<a href="<?=$GAME_CONFIG['URI']?>signup">こちら</a>、ログインは<a href="<?=$GAME_CONFIG['URI']?>signin">こちら</a>から行えます。
</p>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>