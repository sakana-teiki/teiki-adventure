<?php

// 自身をターゲットとして返す
class OwnTarget extends Target {
  function resolve() {
    return [$this->unit]; // 自身をターゲットとして返す 返す内容は配列でないといけないことに注意
  }

  function text() {
    return "自";
  }
}

// ヘイトの高い順に敵を指定数ターゲットする、同値はランダム
// 生きている敵が指定数より少ないときには指定数よりターゲット数が少なくなる
class SomeEnemyTarget extends Target {
  function resolve() {
    // 生きている敵を取得しシャッフル
    $livingEnemies = $this->battle->getLivingEnemies($this->unit->id());
    shuffle($livingEnemies); 

    // ヘイトの高い順に並び替え
    usort($livingEnemies, function ($a, $b) {
      $hateA = $this->unit->hate($a->id());
      $hateB = $this->unit->hate($b->id());
      return $hateB - $hateA;
    });

    // 指定数だけ切り出してターゲットとして返す
    return array_slice($livingEnemies, 0, $this->value);
  }

  function text() {
    return 1 < $value ? "敵{$value}" : "敵";
  }
}

// HP割合が低い順に味方を指定数ターゲットする、同値はランダム
// 生きている味方が指定数より少ないときには指定数よりターゲット数が少なくなる
class SomeWeakenedAllyTarget extends Target {
  function resolve() {
    // 生きている味方を取得しシャッフル
    $livingAllies = $this->battle->getLivingAllies($this->unit->id());
    shuffle($livingAllies);

    // HPの低い順に並び替え
    usort($livingAllies, function ($a, $b) {
      $hpRateA = $a->hp() / $a->mhp();
      $hpRateB = $b->hp() / $b->mhp();
      return $hpRateA - $hpRateB;
    });

    // 指定数だけ切り出してターゲットとして返す
    return array_slice($livingAllies, 0, $this->value);
  }

  function text() {
    return 1 < $value ? "弱味{$value}" : "弱味";
  }
}

// 敵全てをターゲットする
class AllEnemyTarget extends Target {
  function resolve() {
    return $this->battle->getLivingEnemies($this->unit->id());
  }

  function text() {
    return "敵全";
  }
}

// 味方全てをターゲットする
class AllAllyTarget extends Target {
  function resolve() {
    return $this->battle->getLivingAllies($this->unit->id());
  }

  function text() {
    return "味方全体";
  }
}

?>