<?php
/*
  アイコンを表示するコンポーネントです。
  このコンポーネントは以下の値を受け取ります。

  $COMPONENT_ICON['src']:string
  表示するアイコンのURLです。未定義あるいは空文字列の場合noimage表示になります。

  $COMPONENT_ICON['title']:string
  表示するアイコンのキャプションです。未定義あるいは空文字列の場合はalt属性が省略されます。また、srcの指定が無効な場合は表示されません。
*/
?>

<div class="icon-wrapper">
<?php if (isset($COMPONENT_ICON['src']) && $COMPONENT_ICON['src'] != '') {?>
  <img class="icon" src="<?=htmlspecialchars($COMPONENT_ICON['src'])?>" <?= isset($COMPONENT_ICON['title']) && $COMPONENT_ICON['title'] != '' ? 'title="'.htmlspecialchars($COMPONENT_ICON['title']).'"' : '' ?>/>
<?php } else {?>
  <div class="icon icon-noimage"></div>
<?php }?>
</div>
