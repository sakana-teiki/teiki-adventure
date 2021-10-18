<?php

// テキストを装飾します。

/**
* エスケープ処理を行い、プロフィールの表示向けに装飾タグを適用します。
* 
* @param string $profile 変換前のプロフィール文です。
* @return array 装飾タグをHTMLタグに変換後のプロフィール文です。
*/
function profileDecoration($profile) {
  $result = htmlspecialchars($profile);
  $result = preg_replace("/&lt;s&gt;(.+)&lt;\/s&gt;/si" , '<span class="small">$1</span>', $result);
  $result = preg_replace("/&lt;l&gt;(.+)&lt;\/l&gt;/si" , '<span class="large">$1</span>', $result);
  $result = preg_replace("/&lt;b&gt;(.+)&lt;\/b&gt;/si" , '<span class="bold">$1</span>',  $result);
  $result = str_replace("\n" , '<br/>',  $result);
  return $result;
}

?>