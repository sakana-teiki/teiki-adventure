<!DOCTYPE html>
<html lang="ja-JP">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="format-detection" content="email=no,telephone=no,address=no">
<title><?php 
  // $PAGE_SETTING['DISABLE_TITLE_TEMPLATE']が定義されており、trueなら設定されたタイトルをそのまま表示
  // そうでなければタイトルテンプレートを付加してタイトルを表示
  if (isset($PAGE_SETTING['DISABLE_TITLE_TEMPLATE']) && $PAGE_SETTING['DISABLE_TITLE_TEMPLATE'] == true) {
    echo $PAGE_SETTING['TITLE'];
  } else {
    echo $PAGE_SETTING['TITLE'].$GAME_CONFIG['TITLE_TEMPLATE'];
  }
?></title>
<link rel="icon" href="<?=$GAME_CONFIG['URI']?>favicon.ico">
<link rel="stylesheet" href="<?=$GAME_CONFIG['URI']?>styles/normalize.css">
<link rel="stylesheet" href="<?=$GAME_CONFIG['URI']?>styles/theme.css">
<script src="<?=$GAME_CONFIG['URI']?>scripts/jquery-3.6.0.min.js"></script>
</head>
<body>
<?php if ($GAME_LOGGEDIN_AS_ADMINISTRATOR) { ?>
  <div style="position:fixed; right:10px; top:10px; pointer-events:none; background: #c2193e; padding: 10px 20px; color: white; font-weight: bold; border-radius: 8px;">管理者モード</div>
<?php } ?>
<header>
  <section id="title">
    <a id="title-logo" href="<?php if ($GAME_LOGGEDIN) { echo $GAME_CONFIG['HOME_URI']; } else { echo $GAME_CONFIG['TOP_URI']; }?>">Teiki Adventure</a>
  </section>
</header>
<section id="columns">
<nav id="sub-column">
<?php include GETENV('GAME_ROOT').'/components/sidemenu.php'; ?>
</nav>
<main id="main-column">