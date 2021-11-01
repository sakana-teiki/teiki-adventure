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