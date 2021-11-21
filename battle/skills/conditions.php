<?php 

// 常時発動可能
class Always extends Condition {
  function resolve() {
    return true;
  }

  function text() {
    return "通常時";
  }
}

// N行動ごと
class PerActions extends Condition {
  function resolve() {
    return $this->unit->actionCount() % $this->value == 0;
  }
  
  function text() {
    return "{$value}行動毎";
  }
}

// HPが50%以下の味方がいるとき
class WeakenedAllyExists extends Condition {
  function resolve() {
    $livingAllies = $this->battle->getLivingAllies($this->unit->id()); // 生きている味方を取得

    foreach ($livingAllies as $ally) {
      if ($ally->hp() / $ally->mhp() <= 0.5) {
        return true; // HP50%以下の味方がいるなら実行可能
      }
    }

    return false; // いなければ実行不可
  }

  function text() {
    return "味方重傷時";
  }
}

?>