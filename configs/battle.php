<?php
/*
  このファイルでは主に戦闘中に使用する値を設定します。
*/

// 設定
$BATTLE_CONFIG['MAX_ROUND']        = 30; // 最大ラウンドを指定します。
$BATTLE_CONFIG['BASIC_DODGE_RATE'] = 20; // 基礎回避率を指定します。

$BATTLE_CONFIG['HP_RATE'] = array( // ステータスごとのHP増加量を指定します。
  'ATK' => 2,
  'DEX' => 2,
  'MND' => 1,
  'AGI' => 1,
  'DEF' => 5
);
$BATTLE_CONFIG['GUARANTEED_HP'] = 100; // 最低保証HPを指定します。

$BATTLE_CONFIG['SP_RATE'] = array( // ステータスごとのSP増加量を指定します。
  'ATK' => 3,
  'DEX' => 3,
  'MND' => 5,
  'AGI' => 2,
  'DEF' => 1
);
$BATTLE_CONFIG['GUARANTEED_SP'] = 100; // 最低保証SPを指定します。

?>