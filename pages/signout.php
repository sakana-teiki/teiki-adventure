<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  // セッションの破棄
  $_SESSION = array();
  session_destroy();

  // TOPへリダイレクト
  header('Location:'.$GAME_CONFIG['TOP_URI'], true, 302);
?>