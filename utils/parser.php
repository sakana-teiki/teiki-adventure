<?php

// SQLの実行結果を使いやすい形にパースするための関数群です。

/**
* iconsをGROUP_CONCATで取得した際の結果を連想配列の配列の形に整形します。
* 
* @param string $iconsResult iconsをGROUP_CONCATで取得した際の結果となる文字列です。
* @return array nameとurlをキーに持つ連想配列の配列
*/
function parseIconsResult($iconsResult) {
  if (!$iconsResult) { // 受け取った値が空なら空の連想配列を返す
    return array();
  }

  $iconsResultRows = explode("\n", $iconsResult);

  $icons = array();
  $cnt = count($iconsResultRows);
  for ($i = 0; $i < $cnt; $i += 2) {
    $icons[] = array('name' => $iconsResultRows[$i], 'url' => $iconsResultRows[$i+1]);
  }

  return $icons;
}

/**
* items_master_data_effectsをGROUP_CONCATで取得した際の結果を連想配列の配列の形に整形します。
* 
* @param string $effects items_master_data_effectsをGROUP_CONCATで取得した際の結果となる文字列です。
* @return array effectとvalueをキーに持つ連想配列の配列
*/
function parseItemsMasterDataEffects($effectsResult) {
  if (!$effectsResult) { // 受け取った値が空なら空の連想配列を返す
    return array();
  }

  $effectsResultRows = explode("\n", $effectsResult);

  $effects = array();
  $cnt = count($effectsResultRows);
  for ($i = 0; $i < $cnt; $i += 2) {
    $effects[] = array('effect' => $effectsResultRows[$i], 'value' => $effectsResultRows[$i+1]);
  }

  return $effects;
}

/**
* 返信先をGROUP_CONCATで取得した際の結果を連想配列の配列の形に整形します。
* 
* @param string $recipientsResult recipientsをGROUP_CONCATで取得した際の結果となる文字列です。
* @return array nameとENoをキーに持つ連想配列の配列
*/
function parseRecipientsResult($recipientsResult) {
  if (!$recipientsResult) { // 受け取った値が空なら空の連想配列を返す
    return array();
  }

  $recipientsResultRows = explode("\n", $recipientsResult);

  $recipients = array();
  $cnt = count($recipientsResultRows);
  for ($i = 0; $i < $cnt; $i += 2) {
    $recipients[] = array('name' => $recipientsResultRows[$i], 'ENo' => $recipientsResultRows[$i+1]);
  }

  return $recipients;
}

?>