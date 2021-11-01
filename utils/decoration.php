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
  $result = preg_replace("/&lt;s1&gt;(.+)&lt;\/s1&gt;/si" , '<span class="small-1">$1</span>',   $result);
  $result = preg_replace("/&lt;s2&gt;(.+)&lt;\/s2&gt;/si" , '<span class="small-2">$1</span>',   $result);
  $result = preg_replace("/&lt;s3&gt;(.+)&lt;\/s3&gt;/si" , '<span class="small-3">$1</span>',   $result);
  $result = preg_replace("/&lt;s4&gt;(.+)&lt;\/s4&gt;/si" , '<span class="small-4">$1</span>',   $result);
  $result = preg_replace("/&lt;s5&gt;(.+)&lt;\/s5&gt;/si" , '<span class="small-5">$1</span>',   $result);
  $result = preg_replace("/&lt;l1&gt;(.+)&lt;\/l1&gt;/si" , '<span class="large-1">$1</span>',   $result);
  $result = preg_replace("/&lt;l2&gt;(.+)&lt;\/l2&gt;/si" , '<span class="large-2">$1</span>',   $result);
  $result = preg_replace("/&lt;l3&gt;(.+)&lt;\/l3&gt;/si" , '<span class="large-3">$1</span>',   $result);
  $result = preg_replace("/&lt;l4&gt;(.+)&lt;\/l4&gt;/si" , '<span class="large-4">$1</span>',   $result);
  $result = preg_replace("/&lt;l5&gt;(.+)&lt;\/l5&gt;/si" , '<span class="large-5">$1</span>',   $result);
  $result = preg_replace("/&lt;b&gt;(.+)&lt;\/b&gt;/si" ,   '<span class="bold">$1</span>',      $result);
  $result = preg_replace("/&lt;i&gt;(.+)&lt;\/i&gt;/si" ,   '<span class="italic">$1</span>',    $result);
  $result = preg_replace("/&lt;s&gt;(.+)&lt;\/s&gt;/si" ,   '<span class="strike">$1</span>',    $result);
  $result = preg_replace("/&lt;u&gt;(.+)&lt;\/u&gt;/si" ,   '<span class="underline">$1</span>', $result);
  $result = str_replace("\n" , '<br/>',  $result);
  return $result;
}

/**
* エスケープ処理を行い、プロフィールの表示向けの装飾タグを削除します。
* 
* @param string $profile 変換前のプロフィール文です。
* @return array 装飾タグをHTMLタグに変換後のプロフィール文です。
*/
function deleteProfileDecoration($profile) {
  $result = htmlspecialchars($profile);
  $result = preg_replace("/&lt;s1&gt;(.+)&lt;\/s1&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;s2&gt;(.+)&lt;\/s2&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;s3&gt;(.+)&lt;\/s3&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;s4&gt;(.+)&lt;\/s4&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;s5&gt;(.+)&lt;\/s5&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;l1&gt;(.+)&lt;\/l1&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;l2&gt;(.+)&lt;\/l2&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;l3&gt;(.+)&lt;\/l3&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;l4&gt;(.+)&lt;\/l4&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;l5&gt;(.+)&lt;\/l5&gt;/si" , '$1', $result);
  $result = preg_replace("/&lt;b&gt;(.+)&lt;\/b&gt;/si" ,   '$1', $result);
  $result = preg_replace("/&lt;i&gt;(.+)&lt;\/i&gt;/si" ,   '$1', $result);
  $result = preg_replace("/&lt;s&gt;(.+)&lt;\/s&gt;/si" ,   '$1', $result);
  $result = preg_replace("/&lt;u&gt;(.+)&lt;\/u&gt;/si" ,   '$1', $result);
  return $result;
}

/**
* エスケープ処理を行い、装飾タグ及びダイス処理を適用します。
* 
* @param string $message 変換前の文字列です。
* @return array 装飾タグ及びダイス処理を適用したプロフィール文です。
*/

function messageDecoration($message) {
  // プロフィール装飾を適用
  $message = profileDecoration($message);

  // <1d6>を1個ずつ置換
  while (true) {
    $result = preg_replace("/&lt;1d6&gt;/i", '<span class="dice-6">🎲'.mt_rand(1,6).'</span>', $message, 1);

    if ($result == $message) { // 結果が変わらなかった場合はもう<1d6>がないので<1d6>の処理を終了
      break;
    } else {
      $message = $result; // 結果が変わっている場合はまだ<1d6>がある可能性があるので$messageの内容をアップデートし次ループへ
    }
  }

  // <1d100>を1個ずつ置換
  while (true) {
    $result = preg_replace("/&lt;1d100&gt;/i", '<span class="dice-100">🎲'.mt_rand(1,100).'</span>', $message, 1);

    if ($result == $message) { // 結果が変わらなかった場合はもう<1d100>がないので<1d100>の処理を終了
      break;
    } else {
      $message = $result; // 結果が変わっている場合はまだ<1d100>がある可能性があるので$messageの内容をアップデートし次ループへ
    }
  }

  return $message;
}

/**
* エスケープ処理を行い、改行を<br>に変換します。
* 
* @param string $profile 変換前の文字列です。
* @return array 改行を<br>に変換後の文字列です。
*/
function newLineDecoration($string) {
  $result = htmlspecialchars($string);
  $result = str_replace("\n" , '<br/>',  $string);
  return $result;
}
?>