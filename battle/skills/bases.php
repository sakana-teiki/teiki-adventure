<?php

/*-------------------------------------------------------------------------------------------------
  スキルエフェクト発動条件クラス
-------------------------------------------------------------------------------------------------*/

// スキルエフェクト発動条件の基底クラス
class EffectCondition {
  protected int    $value;  // 値 スキルエフェクト発動条件によってはない場合もある
  protected Unit   $unit;   // スキル所持者
  protected Battle $battle; // バトル環境

  function __construct(int $value) {
    $this->text  = '';
    $this->value = $value;
  }

  // 初期化処理 スキル使用者とバトル環境を受け取る
  function init(Unit $unit, Battle $battle) {
    $this->unit   = $unit;
    $this->battle = $battle;
  }

  // スキルエフェクトが発動できるかどうかを返す
  function revolve() {
    return false;
  }

  // テキストを返す
  function text() {
    return '';
  }
}

/*-------------------------------------------------------------------------------------------------
  ターゲットクラス
-------------------------------------------------------------------------------------------------*/

class Target {
  protected int    $value;  // 値 ターゲットによってはない場合もある
  protected Unit   $unit;   // スキル所持者
  protected Battle $battle; // バトル環境

  function __construct(int $value) {
    $this->text   = '';
    $this->value = $value;
  }

  // 初期化処理 スキル使用者とバトル環境を受け取る
  function init(Unit $unit, Battle $battle) {
    $this->unit   = $unit;
    $this->battle = $battle;
  }

  // ターゲットとなるキャラクターの配列を返す 返すのはターゲットが1体であっても配列でなければならないので注意すること
  function revolve() {
    return [];
  }

  // テキストを返す
  function text() {
    return '';
  }
}

/*-------------------------------------------------------------------------------------------------
  スキルエレメントクラス
-------------------------------------------------------------------------------------------------*/

class Element {
  protected int    $value;  // 効果量 スキルエレメントによってはない場合もある
  protected Unit   $unit;   // スキル所持者
  protected Battle $battle; // バトル環境

  function __construct(int $value) {
    $this->text  = '';
    $this->value = $value;
  }

  // 初期化処理 スキル使用者とバトル環境を受け取る
  function init(Unit $unit, Battle $battle) {
    $this->unit   = $unit;
    $this->battle = $battle;
  }

  // ターゲットに対して効果を発動させる
  function revolve(Unit $target) {
    
  }

  // テキストを返す
  function text() {
    return '';
  }
}

/*-------------------------------------------------------------------------------------------------
  スキルエフェクトクラス
-------------------------------------------------------------------------------------------------*/

class SkillEffect {
  protected Target           $target;          // ターゲット
  protected ?EffectCondition $effectCondition; // スキルエフェクト発動条件
  protected array            $elements;        // スキルエレメントの配列
  protected bool             $dodgeable;       // スキルエフェクトが回避可能かどうか（主に回復やバフスキルを回避しないために使用します）
  protected Unit             $unit;            // スキル所持者
  protected Battle           $battle;          // バトル環境

  function __construct(string $targetClassName, int $targetNumber, string $effectConditionClassName, int $effectConditionValue, array $elementMasterDatas, bool $dodgeable) {
    // クラス名からターゲットクラスを生成
    $this->target = new $targetClassName($targetNumber);
    
    // 設定されていればクラス名からスキルエフェクト発動条件クラスを生成、なければnull
    if ($effectConditionClassName === '') {
      $this->effectCondition = null;
    } else {
      $this->effectCondition = new $effectConditionClassName($effectConditionValue);
    }

    // マスタデータから発動条件クラスを生成
    $this->elements = [];
    foreach ($elementMasterDatas as $elementMasterData) {
      $this->elements[] = new $elementMasterData->element($elementMasterData->value);
    }

    // その他の値を設定
    $this->dodgeable = $dodgeable;
  }

  // 初期化処理 スキル使用者とバトル環境を受け取り各要素に渡す
  function init(Unit $unit, Battle $battle) {
    $this->unit   = $unit;
    $this->battle = $battle;

    $this->target->init($unit, $battle);

    if (!is_null($this->effectCondition)) {
      $this->effectCondition->init($unit, $battle);
    }

    foreach ($this->elements as $element) {
      $element->init($unit, $battle);
    }
  }

  // スキルエフェクト発動
  function resolve() {
    // スキルエフェクト発動条件があり、発動条件を満たしていないなら特に何もせず処理を終える
    if (!is_null($this->effectCondition) && !$this->effectCondition->resolve()) {
      return;
    }

    $targets = $this->target->resolve(); // ターゲットとなるキャラクターの配列を取得

    // ターゲットごとに処理を行う
    foreach ($targets as $target) {
      // 回避可能なスキルエフェクトなら回避を試みる
      if ($this->dodgeable && $target->tryDodge($this->unit)) {
        //成功なら処理をスキップして次のターゲットへ
        continue;
      } else {
        // 回避に失敗したらスキルエレメント群を発動させる
        foreach ($this->elements as $element) {
          $element->resolve($target);
        }
      }
    }
  }

  // テキストを返す
  function text() {
    // テキストを生成
    // 例：「SPが30%以上なら敵全：攻撃＆毒」
    $text = '';

    if (!is_null($this->effectCondition)) {
      $text .= $this->effectCondition->text();
    }

    $text .= $this->target->text().':';
  
    $elementTexts = [];
    foreach ($this->elements as $element) {
      $elementTexts[] = $element->text();
    }

    $text .= implode('&', $elementTexts);

    return $text;
  }
}

/*-------------------------------------------------------------------------------------------------
  スキル発動条件クラス
-------------------------------------------------------------------------------------------------*/

// スキル発動条件の基底クラス
class Condition {
  protected int    $value;  // 値 スキル発動条件によってはない場合もある
  protected Unit   $unit;   // スキル所持者
  protected Battle $battle; // バトル環境

  function __construct(int $value) {
    $this->value = $value;
  }

  // 初期化処理 スキル使用者とバトル環境を受け取る
  function init(Unit $unit, Battle $battle) {
    $this->unit   = $unit;
    $this->battle = $battle;
  }

  // スキルが発動できるかどうかを返す
  function revolve() {
    return false;
  }

  // テキストを返す
  function text() {
    return '';
  }
}

/*-------------------------------------------------------------------------------------------------
　スキルクラス
-------------------------------------------------------------------------------------------------*/

class Skill {
  protected string $name;         // スキル名
  protected array  $skillEffects; // 発動するスキルエフェクト群
  protected Unit   $unit;         // スキル所持者
  protected Battle $battle;       // バトル環境
  protected string $lines;        // スキル発動時のセリフ

  function __construct(string $name, array $skillEffectDatas) {
    $this->name = $name;

    // マスタデータからスキルエフェクトをそれぞれ生成
    $this->skillEffects = [];

    foreach ($skillEffectDatas as $skillEffectData) {
      $this->skillEffects[] = new SkillEffect(
        $skillEffectData->target,
        $skillEffectData->target_value,
        $skillEffectData->condition,
        $skillEffectData->condition_value,
        $skillEffectData->elements,
        $skillEffectData->dodgeable
      );
    }
  }

  // 初期化処理 スキル使用者とバトル環境を受け取る
  function init(Unit $unit, Battle $battle) {
    $this->unit   = $unit;
    $this->battle = $battle;

    foreach ($this->skillEffects as $skillEffect) {
      $skillEffect->init($unit, $battle); // スキルエフェクトをそれぞれ初期化
    }
  }

  // 初期化処理 スキル発動時のセリフを設定する プロフィール画面などで扱いやすいようにセリフについては分離している
  function setLines(string $lines) {
    $this->lines = $lines;
  }

  // スキル実行
  function execute() {
    $this->unit->speechDialog($this->lines); // スキルセリフを発話させる

    foreach ($this->skillEffects as $skillEffect) {
      $skillEffect->resolve();
    }
  }

  // スキル名を返す
  function name() {
    return $this->name;
  }

  // スキル説明を返す
  function getDescription() {
    return array(
      'type' => "",
      'name' => $this->name,
      'cond' => "",
      'desc' => ""
    );
  }
}

/*-------------------------------------------------------------------------------------------------
  アクティブスキルクラス
-------------------------------------------------------------------------------------------------*/

class ActiveSkill extends Skill {
  protected int       $cost;      // 発動に必要なコスト
  protected Condition $condition; // スキル発動条件

  function __construct(string $name, int $cost, string $conditionClassName, int $conditionValue, array $skillEffectDatas) {
    parent::__construct($name, $skillEffectDatas);
    $this->cost      = $cost;
    $this->condition = new $conditionClassName($conditionValue); // クラス名からスキル発動条件クラスを生成
  }

  // 初期化処理 スキル使用者とバトル環境を受け取る
  function init(Unit $unit, Battle $battle) {
    parent::init($unit, $battle);
    $this->condition->init($unit, $battle); // スキル発動条件を初期化
  }

  // SPが足りているかどうかを返す
  function isCostOK() {
    return $this->cost <= $this->unit->sp();
  }

  // スキルが実行可能かどうかを返す
  function isExecutable() {
    return $this->condition->resolve();
  }

  // 実行後処理を行う
  function afterExecute() {
    $this->unit->consumeSp($this->cost); // コスト分SPを消費
  }

  // スキル説明を返す
  function getDescription() {
    $skillEffectTexts = [];
    foreach ($this->skillEffects as $skillEffect) {
      $skillEffectTexts[] = $skillEffect->text();      
    }

    return array(
      'type' => "active",
      'name' => $this->name,
      'cond' => "{$this->cost}SP / ".$this->condition->text(),
      'desc' => implode('+', $skillEffectTexts)
    );
  }
}

/*-------------------------------------------------------------------------------------------------
  パッシブスキルクラス
-------------------------------------------------------------------------------------------------*/

class PassiveSkill extends Skill {
  protected string $trigger;         // 発動トリガー
  protected int    $rateNumerator;   // 発動率を分数で表した場合の分子
  protected int    $rateDenominator; // 発動率を分数で表した場合の分母

  function __construct(string $name, string $trigger, int $rateNumerator, int $rateDenominator, array $skillEffectDatas) {
    parent::__construct($name, $skillEffectDatas);

    $this->trigger         = $trigger;
    $this->rateNumerator   = $rateNumerator;
    $this->rateDenominator = $rateDenominator;
  }

  // 発動率チェックを行い発動するかどうかを返す
  function isRateCheckOK() {
    return mt_rand(1, $this->rateDenominator) <= $this->rateNumerator;
  }

  // トリガーを返す
  function getTrigger() {
    return $this->trigger;
  }

  // スキル説明を返す
  function getDescription() {
    $skillEffectTexts = [];
    foreach ($this->skillEffects as $skillEffect) {
      $skillEffectTexts[] = $skillEffect->text();      
    }

    $triggerNames = array(
      'start'    => '戦闘開始時',
      'dodge'    => '回避時',
      'attacked' => '被攻撃時'
    );

    return array(
      'type' => "passive",
      'name' => $this->name,
      'cond' => $triggerNames[$this->trigger],
      'desc' => implode('+', $skillEffectTexts)
    );
  }
}

?>