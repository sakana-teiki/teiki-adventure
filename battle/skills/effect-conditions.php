<?php

// SPがMSPの指定の%以上なら
class SpRemain extends EffectCondition {
  function resolve() {
    return $this->value <= ($this->unit->sp / $this->unit.msp) * 100;
  }

  function text() {
    return '自身のSPが'.$this->value.'%以上なら';
  }
}

?>