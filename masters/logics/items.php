<?php

  // アイテムの使用時効果群です。
  // item_master_data_effectsテーブルのeffectカラムに指定した名称を関数名に設定することでアイテムの使用効果として呼び出されるようになります。
  // $targetが使用したキャラクター、$valueが効果量を表します。
  // 実行に成功した場合はログテキストを、失敗した場合はfalseを返します。
  // 外部から呼び出されたくない関数は関数名の先頭に_を付けます。

  namespace item;

  function GainNP($target, $value) {
    global $GAME_PDO;

    // 効果量分NPを付与
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `NP` = `NP` + :NP
      WHERE
        `ENo` = :target;
    ");

    $statement->bindParam(':NP',     $value);
    $statement->bindParam(':target', $target);

    $result = $statement->execute();

    if (!$result) {
      return false;
    } else {
      return 'NPを'.$value.'獲得しました。';
    }
  }

  function _gainStatus($status, $target, $value) {
    global $GAME_PDO;

    // 効果量分ステータスを付与
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `$status` = `$status` + :value
      WHERE
        `ENo` = :target;
    ");

    $statement->bindParam(':value',  $value);
    $statement->bindParam(':target', $target);

    $result = $statement->execute();

    if (!$result) {
      return false;
    } else {
      return $status.'が'.$value.'成長しました。';
    }
  }

  function GainATK($target, $value) { return _gainStatus('ATK', $target, $value); }
  function GainDEX($target, $value) { return _gainStatus('DEX', $target, $value); }
  function GainMND($target, $value) { return _gainStatus('MND', $target, $value); }
  function GainAGI($target, $value) { return _gainStatus('AGI', $target, $value); }
  function GainDEF($target, $value) { return _gainStatus('DEF', $target, $value); }

?>