<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>announcements"><div class="sidemenu-button">お知らせ</div></a>
<?php if ($GAME_LOGGEDIN) { ?>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>notifications"><div class="sidemenu-button">通知</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>room"><div class="sidemenu-button">全体トークルーム</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>rooms"><div class="sidemenu-button">トークルーム一覧</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>rooms/create"><div class="sidemenu-button">トークルーム作成</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>messages"><div class="sidemenu-button">ダイレクトメッセージ</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>profile/edit"><div class="sidemenu-button">キャラクター設定</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>setting"><div class="sidemenu-button">設定</div></a>
<?php } ?>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>characters"><div class="sidemenu-button">キャラクターリスト</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>forum"><div class="sidemenu-button">掲示板</div></a>
<?php if ($GAME_LOGGEDIN_AS_ADMINISTRATOR) { ?>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>control-panel/"><div class="sidemenu-button">コントロールパネル</div></a>
<?php } ?>
<section class="sidemenu-mini-link-wrapper">
<?php if ($GAME_LOGGEDIN) { ?>
  <a class="sidemenu-mini-link" href="<?=$GAME_CONFIG['URI']?>signout">&gt;&gt; ログアウト</a>
<?php } ?>
</section>
