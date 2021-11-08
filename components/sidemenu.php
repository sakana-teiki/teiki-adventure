<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>announcements"><div class="sidemenu-button">お知らせ</div></a>
<?php if ($GAME_LOGGEDIN) { ?>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>notifications"><div class="sidemenu-button">通知</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>declaration"><div class="sidemenu-button">宣言</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>exploration"><div class="sidemenu-button">探索</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>logs"><div class="sidemenu-button">探索ログ</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>items"><div class="sidemenu-button">アイテム</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>shop"><div class="sidemenu-button">アイテムショップ</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>trade"><div class="sidemenu-button">アイテム送付</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>market"><div class="sidemenu-button">フリーマーケット</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>room"><div class="sidemenu-button">全体トークルーム</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>rooms"><div class="sidemenu-button">トークルーム一覧</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>rooms/create"><div class="sidemenu-button">トークルーム作成</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>messages"><div class="sidemenu-button">ダイレクトメッセージ</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>profile/edit"><div class="sidemenu-button">キャラクター設定</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>skill"><div class="sidemenu-button">戦闘設定</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>setting"><div class="sidemenu-button">ゲーム設定</div></a>
<?php } ?>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>characters"><div class="sidemenu-button">キャラクターリスト</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>rulebook"><div class="sidemenu-button">ルールブック</div></a>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>forum"><div class="sidemenu-button">掲示板</div></a>
<?php if ($GAME_LOGGEDIN_AS_ADMINISTRATOR) { ?>
<a class="sidemenu-button-link" href="<?=$GAME_CONFIG['URI']?>control-panel/"><div class="sidemenu-button">コントロールパネル</div></a>
<?php } ?>
<section class="sidemenu-mini-link-wrapper">
<?php if ($GAME_LOGGEDIN && !$GAME_MAINTENANCE) { // ログイン状態のときのみ表示 また誤ってログアウトすることを防ぐためメンテナンス中はログアウトボタンを表示しない ?>
  <a class="sidemenu-mini-link" href="<?=$GAME_CONFIG['URI']?>signout">&gt;&gt; ログアウト</a>
<?php } ?>
</section>
