<?php
require GETENV('GAME_ROOT').'/configs/battle.php';

require_once GETENV('GAME_ROOT').'/battle/skills/bases.php';
require_once GETENV('GAME_ROOT').'/battle/skills/conditions.php';
require_once GETENV('GAME_ROOT').'/battle/skills/effect-conditions.php';
require_once GETENV('GAME_ROOT').'/battle/skills/elements.php';
require_once GETENV('GAME_ROOT').'/battle/skills/targets.php';

// 各ユニットを管理するクラス
class Unit {
  // このクラスで管理する値の宣言
  private Battle $battle;        // このユニットを管理するバトルクラス
  private int    $id;            // この戦闘におけるID
  private string $name;          // ユニット名
  private int    $actionCount;   // 行動回数
  private bool   $isActed;       // 行動済フラグ
  private bool   $isLiving;      // 生きているかどうかのフラグ
  private array  $hates;         // ヘイト量
  private array  $effects;       // 状態異常の残りターン数群
  private array  $activeSkills;  // アクティブスキル
  private array  $passiveSkills; // パッシブスキル

  private int   $hp;     // 現在HP
  private int   $sp;     // 現在SP
  private int   $mhp;    // 最大HP
  private int   $msp;    // 最大SP
  private array $status; // ステータス

  // 初期化処理1
  function __construct(string $name, array $status, array $skillDatas) {
    // 名前とステータスの設定
    $this->name   = htmlspecialchars($name); // 名前のエスケープ
    $this->status = $status;

    // HP/SPの設定
    $this->mhp =
      $GLOBALS['BATTLE_CONFIG']['GUARANTEED_HP'] + 
      $status['ATK'] * $GLOBALS['BATTLE_CONFIG']['HP_RATE']['ATK'] + 
      $status['DEX'] * $GLOBALS['BATTLE_CONFIG']['HP_RATE']['DEX'] + 
      $status['MND'] * $GLOBALS['BATTLE_CONFIG']['HP_RATE']['MND'] + 
      $status['AGI'] * $GLOBALS['BATTLE_CONFIG']['HP_RATE']['AGI'] + 
      $status['DEF'] * $GLOBALS['BATTLE_CONFIG']['HP_RATE']['DEF'];
    $this->hp = $this->mhp;

    $this->msp =
      $GLOBALS['BATTLE_CONFIG']['GUARANTEED_SP'] + 
      $status['ATK'] * $GLOBALS['BATTLE_CONFIG']['SP_RATE']['ATK'] + 
      $status['DEX'] * $GLOBALS['BATTLE_CONFIG']['SP_RATE']['DEX'] + 
      $status['MND'] * $GLOBALS['BATTLE_CONFIG']['SP_RATE']['MND'] + 
      $status['AGI'] * $GLOBALS['BATTLE_CONFIG']['SP_RATE']['AGI'] + 
      $status['DEF'] * $GLOBALS['BATTLE_CONFIG']['SP_RATE']['DEF'];
    $this->sp = $this->msp;

    // スキルの読み込み
    $this->activeSkills  = [];
    $this->passiveSkills = [];

    foreach ($skillDatas as $skillData) {
      if ($skillData['type'] === 'active') {
        $this->activeSkills[] = new ActiveSkill(
          $skillData['name'],
          $skillData['cost'],
          $skillData['condition'],
          $skillData['condition_value'],
          $skillData['effects']
        );
      } else if ($skillData['type'] === 'passive') {
        $this->passiveSkills[] = new PassiveSkill(
          $skillData['name'],
          $skillData['trigger'],
          $skillData['rate_numerator'],
          $skillData['rate_denominator'],
          $skillData['effects']
        );
      }
    }

    // アクティブスキルの末尾に通常攻撃を登録
    $this->activeSkills[] = new ActiveSkill(
      '通常攻撃',
      '0',
      'Always',
      0,
      json_decode('[{"target": "SomeEnemyTarget", "elements": [{"value": 30, "element": "AttackElement"}], "condition": "", "dodgeable": true, "target_value": 1, "condition_value": 0}]')
    );
    
    // 状態異常の初期化
    $this->effects = array(
      'poison' => 0
    );
    
    // その他の値の初期化
    $this->actionCount = 0;
    $this->isActed     = false;
    $this->isLiving    = true;
    $this->hates       = [];
  }

  // 初期化処理2 ユニット生成時には受け取れない値はこれを呼び出してセット
  function init(Battle $battle, int $id) {
    $this->battle = $battle;
    $this->id     = $id;

    foreach ($this->activeSkills as $skill) {
      $skill->init($this, $battle);
    }

    foreach ($this->passiveSkills as $skill) {
      $skill->init($this, $battle);
    }
  }

  // 自身のユニット名を返す
  function name() {
    return $this->name;
  }

  // 自身の現在HPを返す
  function hp() {
    return $this->hp;
  }

  // 自身の現在SPを返す
  function sp() {
    return $this->sp;
  }

  // 自身の最大HPを返す
  function mhp() {
    return $this->mhp;
  }

  // 自身の最大SPを返す
  function msp() {
    return $this->msp;
  }

  // 自身のATKを返す
  function atk() {
    return $this->status['ATK'] * ($this->effects['poison'] ? 0.8 : 1); // 毒効果中は0.8倍になる
  }

  // 自身のDEXを返す
  function dex() {
    return $this->status['DEX'] * ($this->effects['poison'] ? 0.8 : 1); // 毒効果中は0.8倍になる
  }

  // 自身のMNDを返す
  function mnd() {
    return $this->status['MND'];
  }

  // 自身のAGIを返す
  function agi() {
    return $this->status['AGI'] * ($this->effects['poison'] ? 0.8 : 1); // 毒効果中は0.8倍になる
  }

  // 自身のDEFを返す
  function def() {
    return $this->status['DEF'];
  }

  // 自身のIDを返す
  function id() {
    return $this->id;
  }

  // 現在の行動回数を返す
  function actionCount() {
    return $this->actionCount;
  }

  // 生きているかどうかを返す
  function isLiving() {
    return $this->isLiving;
  }

  // 行動可能かどうかを返す（生きていて行動済みではない）
  function isActable() {
    return $this->isLiving() && !$this->isActed;
  }

  // 対象へのヘイトを取得する
  function hate(int $unitId) {
    return isset($this->hates[$unitId]) ? $this->hates[$unitId] : 0; // ヘイトが存在していればその値、ヘイトが存在していなかった場合は0
  }

  // 対象へのヘイトを蓄積する
  function gainHate(int $hate, Unit $target, bool $showMessage = false) {
    if (isset($this->hates[$target->id()])) {
      $this->hates[$target->id()] += $hate; // 既に対象へのヘイトが存在していた場合は加算
    } else {
      $this->hates[$target->id()] = $hate; // まだ対象へのヘイトが存在していなかった場合はヘイトを設定
    }

    if ($showMessage) {
      $this->battle->log('<div class="result">'.$this->name.'は'.$target->name().'へのヘイトが高まった！</div>');
    }
  }

  // SPを消費する
  function consumeSp(int $cost) {
    $this->sp -= $cost;
  }

  // 回避を試行する
  function tryDodge(Unit $attacker) {
    $dodgeRate = $GLOBALS['BATTLE_CONFIG']['BASIC_DODGE_RATE'] + $this->agi() - $attacker->dex();
    // 回避率 = 基礎回避率 + 自身のAGI% - 攻撃者のDEX%

    if (mt_rand(1, 100) <= $dodgeRate) {
      // 回避成功時、メッセージを表示しtrueを返す
      $this->battle->log('<div class="result">'.$this->name.'は攻撃を回避した！</div>');
      $this->dispatchPassiveSkill('dodge'); // 回避時スキルを発動させる
      return true;
    } else {
      // 回避失敗時、falseを返す
      return false;
    }
  }

  // 攻撃を受ける
  function gainAttack(int $potency, Unit $attacker) {
    $basicDamage = (10 + $attacker->atk()) * $potency / 50;                       // 基礎ダメージ (攻撃者のATK+10) × 威力 ÷ 50
    $damage      = mt_rand(floor($basicDamage * 0.8), floor($basicDamage * 1.2)); // ダメージ量 基礎ダメージ×0.8 ～ 基礎ダメージ×1.2

    $this->battle->log('<div class="result">');
    if (mt_rand(1, 100) <= $attacker->dex()) { // 攻撃者のDEX%の確率でクリティカル
      $this->battle->log('クリティカル！');
      $damage *= 2; // クリティカル時はダメージを2倍に
    }

    if (100 <= $damage) {
      $damageRank = ' damage-gte100';
    } else if (50 <= $damage) {
      $damageRank = ' damage-gte50';
    } else {
      $damageRank = '';
    }

    $this->hp -= $damage;
    $this->battle->log($this->name.'は<span class="damage'.$damageRank.'">'.$damage.'</span>点のダメージを受けた！(現在HP：'.$this->hp.')</div>');
    $this->gainHate($damage * $attacker->def(), $attacker); // 「受けたダメージ×攻撃者のDEF」分ヘイトを蓄積する
    $this->dispatchPassiveSkill('attacked'); // 被攻撃時スキルを発動させる
  }

  // 回復を受ける
  function gainHeal(int $potency, Unit $healer) {
    $basicHeal = (10 + $healer->mnd()) * $potency / 50;                     // 基礎回復量 (ヒーラーのMND+10) × 威力 ÷ 50
    $heal      = mt_rand(floor($basicHeal * 0.8), floor($basicHeal * 1.2)); // 回復量 基礎回復量×0.8 ～ 基礎回復量×1.2
    
    $formarHp  = $this->hp; // 回復前のHP
    $this->hp += $heal;     // 回復する
    if ($this->mhp < $this->hp) {
      $this->hp = $this->mhp; // MHPを超えていてらMHPまで切り詰め
    }
    $actualHealed = $this->hp - $formarHp; // 実際の回復量を計算

    $this->battle->log("{$this->name}はHPが{$actualHealed}点回復した！(現在HP：{$this->hp})");
    $this->battle->log('<div class="result">'.$this->name.'はHPが<span class="heal">'.$actualHealed.'</span>点回復した！(現在HP:'.$this->hp.')');
  }

  function gainAgi(int $potency) {
    $this->battle->log('<div class="result">'.$this->name.'のAGIが増加した！</div>');
    $this->status['AGI'] += $potency;
  }

  // 毒を受ける
  function gainPoison(int $turn) {
    $this->effect['poison'] += $turn;
    $this->battle->log('<div class="result">'.$this->name.'は毒を'.$turn.'ターン分受けた！</div>');
  }

  // 指定トリガーのパッシブスキルを発動する
  function dispatchPassiveSkill(string $trigger) {
    foreach ($this->passiveSkills as $skill) {
      if ($trigger === $skill->getTrigger() && $skill->isRateCheckOK()) {
        // パッシブスキル配列からトリガーにマッチするものを検索し発動率による条件を満たしたら
        $this->battle->log($this->name.'の<span class="skill-name">'.$skill->name().'！</span>');
        $skill->execute(); // 実行
      }
    }
  }

  // 1回分の行動を行う
  function action() {
    $this->actionCount++; // 行動回数カウントを+1

    foreach ($this->activeSkills as $skill) {
      if ($skill->isCostOK() && $skill->isExecutable()) {
        // 実行コストが足りていて実行可能なら
        $this->battle->log('<section class="action">');
        $this->battle->log($this->name.'の<span class="skill-name">'.$skill->name().'！</span>('.$this->actionCount.'行動目)');
        // 名前、実行するスキル名、行動回数を表示し
        $skill->execute();      // 実行
        $skill->afterExecute(); // 実行後処理を行う
        $this->battle->log('</section>');
        return;
        // 通常攻撃は必ず末尾に登録されておりかつ実行可能になっているので
        // 他に実行できるスキルがなければ通常攻撃になる
      }
    }    
  }

  // ターン行動を行う
  function act() {
    $this->action();

    if (mt_rand(1, 100) <= $this->agi() ) { // AGI%の確率で連続行動
      $this->battle->log('<div class="action-twice">連続行動！</div>');
      $this->action();
    }

    $this->isActed = true; // 行動済フラグをONに
  }

  // セットアップ処理 行動済ステータスを未行動（false）に
  function setup() {
    $this->isActed = false;
  }

  // クリーンアップ処理
  function cleanup() {
    // 生きていて毒が残っているなら毒ダメージを受ける
    if ($this->isLiving && 0 < $this->effects['poison']) {
      $poisonDamage = mt_rand(5, 15); // 毒ダメージ量（5～15）
    
      $this->hp -= $poisonDamage;
      $this->effects['poison']--;

      if (0 < $this->effects['poison']) {
        // まだターン数が残っている場合
        $this->battle->log('<div class="result">'.$this->name.'は毒により'.$poisonDamage.'のダメージを受けた！(残りターン:'.$this->effects['poison'].')</div>');
      } else {
        // 治癒した場合
        $this->battle->log('<div class="result">'.$this->name.'は毒により'.$poisonDamage.'のダメージを受けた！</div>');
        $this->battle->log('<div class="result">'.$this->name.'の毒は治った！</div>');
      }
    }

    // クリーンアップ時生きていてHP0以下なら戦闘不能に
    if ($this->isLiving && $this->hp <= 0) { 
      $this->isLiving = false;
      $this->battle->log('<div class="result">'.$this->name.'は倒れた……</div>');
    }
  }
}

// 戦闘を管理するクラス
class Battle {
  // このクラスで管理する値の宣言
  private array  $allies;  // 味方側ユニットの配列
  private array  $enemies; // 敵側ユニットの配列
  private array  $units;   // ユニット全体の配列
  private int    $round;   // 現在のラウンド
  private string $result;  // 勝敗結果
  private string $log;     // 生成されたログ
  
  // 初期化処理 各ユニットを受け取り初期化
  function __construct(array $allies, array $enemies) {
    $this->allies  = $allies;
    $this->enemies = $enemies;
    $this->units   = array_merge($allies, $enemies);

    $unitIdDealer = 0; // IDを割り振るための変数

    foreach ($this->allies as $unit) { // 味方チームに一意のIDを割り振る
      $unit->init($this, $unitIdDealer);
      $unitIdDealer++;
    }

    foreach ($this->enemies as $unit) { // 敵チームに一意のIDを割り振る
      $unit->init($this, $unitIdDealer);
      $unitIdDealer++;
    }

    $this->round = 0;
    $this->log   = '';
  }

  // バトルログを追加
  function log(string $log) { 
    $this->log .= $log;
  }

  // 受け取ったユニット配列から生きているユニットだけを抽出して返す
  function extractLiving(array $units) {
    return array_values(array_filter($units, function ($unit) { return $unit->isLiving(); }));
  }

  // 受け取ったユニット配列から行動可能なユニットだけを抽出して返す
  function extractActable(array $units) {
    return array_values(array_filter($units, function ($unit) { return $unit->isActable(); }));
  }

  // 受け取ったユニット配列をAGI降順に並び替えたものを返す
  function sortUnitsByAgi(array $units) {
    usort($units, function ($a, $b) { return $b->agi() - $a->agi(); });
    return $units;
  }

  // 指定IDのユニットから見た味方チームを取得
  function getAllies(int $unitId) {
    foreach ($this->allies as $ally) { // 味方チームに対象のIDがいれば味方チームを返す
      if ($ally->id() === $unitId) {
        return $this->allies;
      }
    }

    return $this->enemies; // 居なければ敵チームを返す
  }

  // 指定IDのユニットから見た敵チームを取得
  function getEnemies(int $unitId) {
    foreach ($this->allies as $ally) { // 味方チームに対象のIDがいれば敵チームを返す
      if ($ally->id() === $unitId) {
        return $this->enemies;
      }
    }

    return $this->allies; // 居なければ味方チームを返す
  }

  // 生きているユニットを取得
  function getLivingUnits() {
    return $this->extractLiving($this->units);
  }

  // 指定IDのユニットから見た生きている味方チームを取得
  function getLivingAllies(int $unitId) {
    return $this->extractLiving($this->getAllies($unitId));
  }

  // 指定IDのユニットから見た生きている敵チームを取得
  function getLivingEnemies(int $unitId) {
    return $this->extractLiving($this->getEnemies($unitId));
  }

  // 指定トリガーのパッシブスキルを発動する
  function dispatchPassiveSkill(string $trigger) {
    // 生きているユニットを取得してAGI降順に並び替え
    $livingUnitsSortByAgi = $this->sortUnitsByAgi($this->getLivingUnits());

    // その順番でパッシブスキルを実行
    foreach ($livingUnitsSortByAgi as $unit) {
      $unit->dispatchPassiveSkill($trigger);
    }
  }

  // 現在の状況を判定
  function judge() {
    $livingAlliesNumber  = count($this->extractLiving($this->allies));  // 生き残っている味方チームの数を取得
    $livingEnemiesNumber = count($this->extractLiving($this->enemies)); // 生き残っている敵チームの数を取得

    if (0 < $livingAlliesNumber && $livingEnemiesNumber === 0) {
      // 味方が生き残っていて敵が全員戦闘不能 → 勝利
      return 'win';
    } else if ($livingAlliesNumber === 0 && 0 < $livingEnemiesNumber) {
      // 味方が全員戦闘不能で敵が生き残っている → 敗北
      return 'lose';
    } else if ($livingAlliesNumber === 0 && $livingEnemiesNumber === 0) {
      // 味方も敵も全員戦闘不能 → 引き分け
      return 'even';
    } else if ($GLOBALS['BATTLE_CONFIG']['MAX_ROUND'] <= $this->round) {
      // どちらも生き残っているが現在ラウンドが最大ラウンド以上 → 引き分け
      return 'even';
    } else {
      // そのどれでもない → 戦闘継続
      return 'continue';
    }
  }

  // 戦闘を行う
  function execute() {
    $this->log('<section class="battle">');

    $this->log('<section class="battle-start">');
    $this->log('<div class="battle-start-call">BATTLE START</div>');
    $this->log('<section class="actions">');
    $this->dispatchPassiveSkill('start'); // 戦闘開始時スキルを発動させる
    $this->log('</section>');
    $this->log('</section>');

    // ラウンドループ
    while(true) {
      $this->round++;

      $this->log('<section class="round">');
      $this->log('<div class="round-start">Round <span class="round-count">'.$this->round.'</span></div>');

      $this->log('<section class="statuses">');

      for ($i = 0; $i < 2; $i++) {
        // チームごとにセットアップ処理とステータス表示を行う
        $this->log('<section class="team">');
        $targetTeamUnits = $this->extractLiving($i ? $this->enemies : $this->allies);

        foreach ($targetTeamUnits as $unit) {
          $unit->setup();

          $this->log('<section class="unit">');
          $this->log('<div class="unit-name">'.$unit->name().'</div>');

          $this->log('<div class="statusbars">');

          $this->log('<div class="statusbar">');
          $this->log('<div class="statusbar-desc">');
          $this->log('<div class="statusbar-key">HP</div>');
          $this->log('<div class="statusbar-value">'.$unit->hp().' / '.$unit->mhp().'</div>');
          $this->log('</div>');
          $this->log('<div class="gauge-wrapper">');
          $this->log('<div class="gauge" style="width: '.($unit->hp() / $unit->mhp() * 100).'%;"></div>');
          $this->log('</div>');
          $this->log('</div>');

          $this->log('<div class="statusbar">');
          $this->log('<div class="statusbar-desc">');
          $this->log('<div class="statusbar-key">SP</div>');
          $this->log('<div class="statusbar-value">'.$unit->sp().' / '.$unit->msp().'</div>');
          $this->log('</div>');
          $this->log('<div class="gauge-wrapper">');
          $this->log('<div class="gauge" style="width: '.($unit->sp() / $unit->msp() * 100).'%;"></div>');
          $this->log('</div>');
          $this->log('</div>');

          $this->log('</div>');
          
          $this->log('</section>');
        }

        $this->log('</section>');
      }

      $this->log('</section>');
      $this->log('<section class="turns">');

      // ターンループ
      while(true) {
        // 全ユニットから行動可能なユニットを抽出
        $actableUnits = $this->extractActable($this->units);

        if (0 < count($actableUnits)) { // 行動可能なユニットがいれば
          $this->log('<section class="turn">');

          $actor = $this->sortUnitsByAgi($actableUnits)[0]; // それをAGI降順に並び替え最もAGIが速いものを行動者とし
          
          $this->log('<div class="actor">'.$actor->name().'のターン！</div>');
          $this->log('<section class="actions">');

          $actor->act(); // 行動実行

          $this->log('</section>');
          $this->log('</section>');
        } else { // 行動可能なユニットがいなければ
          break; // そのラウンドは終了処理へ
        }
      }

      $this->log('</section>');
      $this->log('<section class="clean-up">');

      foreach ($this->units as $unit) {
        $unit->cleanup();
      }

      $this->log('</section>');

      $judge = $this->judge(); // 戦闘判定
      
      if ($judge !== 'continue') {
        // 判定結果が戦闘継続でない場合戦闘終了
        $this->log('<section class="battle-result">');

        if ($judge === 'win') {
          $this->log('戦闘に勝利した！');
        } else if ($judge == 'lose') {
          $this->log('戦闘に敗北した……');
        } else {
          $this->log('決着がつかなかった……');
        }

        $this->result = $judge;

        $this->log('</section>');

        break; // ラウンドループから抜ける
      }

      $this->log('</section>');

      // 判定結果が戦闘継続の場合次のラウンドへ
    }

    return array(
      'result' => $this->result,
      'log'    => $this->log
    );
  }
}

?>