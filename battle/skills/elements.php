<?php

// 対象に攻撃を行うスキルエレメント
class AttackElement extends Element {
  function resolve(Unit $target) {
    $target->gainAttack($this->value, $this->unit);
  }

  function text() {
    return '攻撃';
  }
}

// 対象のヘイトを増加させるスキルエレメント
class HateElement extends Element {
  function resolve(Unit $target) {
    $target->gainHate($this->value, $this->unit, true);
  }

  function text() {
    return 'HATE増';
  }
}

// 対象に回復を行うスキルエレメント
class HealElement extends Element {
  function resolve(Unit $target) {
    $target->gainHeal($this->value, $this->unit);
  }

  function text() {
    return 'HP回復';
  }
}

// AGIを増加させるスキルエレメント
class GainAgiElement extends Element {
  function resolve(Unit $target) {
    $target->gainAgi($this->value);
  }

  function text(){
    return 'AGI増';
  }
}

// 対象に毒を与えるスキルエレメント
class PoisonElement extends Element {
  function resolve(Unit $target) {
    $target->gainPoison($this->value);
  }

  function text() {
    return '毒';
  }
}

?>