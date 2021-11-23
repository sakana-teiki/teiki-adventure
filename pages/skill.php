<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  require_once GETENV('GAME_ROOT').'/battle/skills/bases.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/conditions.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/effect-conditions.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/elements.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/targets.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('atk', ['non-empty', 'non-negative-integer']) ||
      !validatePOST('dex', ['non-empty', 'non-negative-integer']) ||
      !validatePOST('mnd', ['non-empty', 'non-negative-integer']) ||
      !validatePOST('agi', ['non-empty', 'non-negative-integer']) ||
      !validatePOST('def', ['non-empty', 'non-negative-integer']) ||
      !validatePOST('lines_start',         ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_dodge',         ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_dodged',        ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_healed',        ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_healed_own',    ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_normal_attack', ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_defeat',        ['single-line', 'disallow-special-chars']) ||    
      !validatePOST('lines_killed',        ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_killed_ally',   ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_critical',      ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_criticaled',    ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_win',           ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_even',          ['single-line', 'disallow-special-chars']) ||
      !validatePOST('lines_lose',          ['single-line', 'disallow-special-chars']) ||
      !validatePOST('selected_skills', [])
    ) {
      responseError(400);
    }

    // ステータス合計量の計算
    $sum =
      intval($_POST['atk']) +
      intval($_POST['dex']) +
      intval($_POST['mnd']) +
      intval($_POST['agi']) +
      intval($_POST['def']);

    // 登録しようとしているスキル及びスキルセリフを検証
    $postedSelectedSkills = json_decode($_POST['selected_skills']);
    
    // 送信されたスキル設定群の型が配列でなければ400(Bad Request)を返して処理を中断
    if (!is_array($postedSelectedSkills)) {
      responseError(400);
    }

    // 送信されたスキル設定群の検証
    $selectedSkills   = [];
    $selectedSkillIds = []; // 後の検証で利用するためIDのみを抽出したものも一緒に取り出す
    foreach ($postedSelectedSkills as $postedSelectedSkill) {
      // 以下の条件のいれかを満たせば400(Bad Request)を返して処理を中断
      if (
        !isset($postedSelectedSkill->skill)     || // 送信されたデータにskillを含まないものがある
        !isset($postedSelectedSkill->lines)     || // 送信されたデータにlinesを含まないものがある
        !is_int($postedSelectedSkill->skill)    || // skillの型がintでないものがある
        !is_string($postedSelectedSkill->lines) || // linesの型がstringでないものがある
        $postedSelectedSkill->skill <= 0        || // skillの値が0以下
        !validateString($postedSelectedSkill->lines, ['single-line', 'disallow-special-chars']) // linesが単一行でない、あるいは特殊文字が含まれている
      ) {
        responseError(400);
      } else {
        // そうでなければ選択されたスキルに追加
        $selectedSkills[]   = $postedSelectedSkill;
        $selectedSkillIds[] = $postedSelectedSkill->skill;
      }
    }

    // スキルの重複検証
    // 重複を取り除いた結果、スキルの数が変わっていれば400(Bad Request)を返して処理を中断
    if (count($selectedSkillIds) !== count(array_unique($selectedSkillIds))) {
      responseError(400);
    }    

    // キャラクターのステータスを取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `ATK`,
        `DEX`,
        `MND`,
        `AGI`,
        `DEF`
      FROM
        `characters`
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result   = $statement->execute();
    $statuses = $statement->fetch();

    if (!$result || !$statuses) {
      // SQLの実行や実行結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
      responseError(500);
    }

    // 設定可能なスキルのID群を取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `skill_id`
      FROM
        `skills_master_data`
      WHERE
        (`required_status` IS NULL) OR
        (`required_status` = 'ATK' AND `required_status_value` <= :ATK) OR
        (`required_status` = 'DEX' AND `required_status_value` <= :DEX) OR
        (`required_status` = 'MND' AND `required_status_value` <= :MND) OR
        (`required_status` = 'AGI' AND `required_status_value` <= :AGI) OR
        (`required_status` = 'DEF' AND `required_status_value` <= :DEF);
    ");
    
    $statement->bindParam(':ATK', $statuses['ATK']);
    $statement->bindParam(':DEX', $statuses['DEX']);
    $statement->bindParam(':MND', $statuses['MND']);
    $statement->bindParam(':AGI', $statuses['AGI']);
    $statement->bindParam(':DEF', $statuses['DEF']);

    $result = $statement->execute();

    if (!$result) {
      // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      responseError(500);
    }
    
    $settableSkillIds = $statement->fetchAll();

    // 設定可能なスキルでないものを設定しようとしていないか検証
    foreach ($selectedSkills as $selectedSkill) {
      $found = false;

      foreach ($settableSkillIds as $settableSkillId) {
        if ($selectedSkill->skill == $settableSkillId['skill_id']) {
          $found = true;
          break;
        }
      }

      if (!$found) {
        responseError(403); // 設定可能なスキルでないものを設定しようとしていた場合は403(Forbidden)を返して処理を中断
      }
    }

    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // ステータスの反映
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `NP`  = `NP`  - :sum,
        `ATK` = `ATK` + :atk,
        `DEX` = `DEX` + :dex,
        `MND` = `MND` + :mnd,
        `AGI` = `AGI` + :agi,
        `DEF` = `DEF` + :def
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':sum', $sum         , PDO::PARAM_INT);
    $statement->bindParam(':atk', $_POST['atk'], PDO::PARAM_INT);
    $statement->bindParam(':dex', $_POST['dex'], PDO::PARAM_INT);
    $statement->bindParam(':mnd', $_POST['mnd'], PDO::PARAM_INT);
    $statement->bindParam(':agi', $_POST['agi'], PDO::PARAM_INT);
    $statement->bindParam(':def', $_POST['def'], PDO::PARAM_INT);
    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // 更新に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // 既に登録されているスキル設定の削除
    $statement = $GAME_PDO->prepare("
      DELETE FROM
        `characters_skills`
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // 削除に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // スキル設定の登録
    foreach ($selectedSkills as $selectedSkill) {
      $statement = $GAME_PDO->prepare("
        INSERT INTO `characters_skills` (
          `ENo`,
          `skill`,
          `lines`
        ) VALUES (
          :ENo,
          :skill,
          :lines
        );
      ");

      $statement->bindParam(':ENo',   $_SESSION['ENo']);
      $statement->bindParam(':skill', $selectedSkill->skill);
      $statement->bindParam(':lines', $selectedSkill->lines);

      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // 更新に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
    }

    // セリフ設定の登録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `characters_battle_lines` (
        `ENo`,
        `start`,
        `dodge`,
        `dodged`,
        `healed`,
        `healed_own`,
        `normal_attack`,
        `defeat`,
        `killed`,
        `killed_ally`,
        `critical`,
        `criticaled`,
        `win`,
        `even`,
        `lose`
      ) VALUES (
        :ENo,
        :start,
        :dodge,
        :dodged,
        :healed,
        :healed_own,
        :normal_attack,
        :defeat,
        :killed,
        :killed_ally,
        :critical,
        :criticaled,
        :win,
        :even,
        :lose
      )

      ON DUPLICATE KEY UPDATE
        `start`         = :start,
        `dodge`         = :dodge,
        `dodged`        = :dodged,
        `healed`        = :healed,
        `healed_own`    = :healed_own,
        `normal_attack` = :normal_attack,
        `defeat`        = :defeat,
        `killed`        = :killed,
        `killed_ally`   = :killed_ally,
        `critical`      = :critical,
        `criticaled`    = :criticaled,
        `win`           = :win,
        `even`          = :even,
        `lose`          = :lose
    ");

    $statement->bindParam(':ENo',           $_SESSION['ENo']);
    $statement->bindParam(':start',         $_POST['lines_start']);
    $statement->bindParam(':dodge',         $_POST['lines_dodge']);
    $statement->bindParam(':dodged',        $_POST['lines_dodged']);
    $statement->bindParam(':healed',        $_POST['lines_healed']);
    $statement->bindParam(':healed_own',    $_POST['lines_healed_own']);
    $statement->bindParam(':normal_attack', $_POST['lines_normal_attack']);
    $statement->bindParam(':defeat',        $_POST['lines_defeat']);
    $statement->bindParam(':killed',        $_POST['lines_killed']);
    $statement->bindParam(':killed_ally',   $_POST['lines_killed_ally']);
    $statement->bindParam(':critical',      $_POST['lines_critical']);
    $statement->bindParam(':criticaled',    $_POST['lines_criticaled']);
    $statement->bindParam(':win',           $_POST['lines_win']);
    $statement->bindParam(':even',          $_POST['lines_even']);
    $statement->bindParam(':lose',          $_POST['lines_lose']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // 更新に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // ここまで全て成功した場合はコミット
    $GAME_PDO->commit();
  }

  // DBからキャラクターの戦闘設定を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `characters`.`ENo`,
      `characters`.`NP`,
      `characters`.`ATK`,
      `characters`.`DEX`,
      `characters`.`MND`,
      `characters`.`AGI`,
      `characters`.`DEF`,
      `characters_battle_lines`.`start`         AS `lines_start`,
      `characters_battle_lines`.`dodge`         AS `lines_dodge`,
      `characters_battle_lines`.`dodged`        AS `lines_dodged`,
      `characters_battle_lines`.`healed`        AS `lines_healed`,
      `characters_battle_lines`.`healed_own`    AS `lines_healed_own`,
      `characters_battle_lines`.`normal_attack` AS `lines_normal_attack`,
      `characters_battle_lines`.`defeat`        AS `lines_defeat`,
      `characters_battle_lines`.`killed`        AS `lines_killed`,
      `characters_battle_lines`.`killed_ally`   AS `lines_killed_ally`,
      `characters_battle_lines`.`critical`      AS `lines_critical`,
      `characters_battle_lines`.`criticaled`    AS `lines_criticaled`,
      `characters_battle_lines`.`win`           AS `lines_win`,
      `characters_battle_lines`.`even`          AS `lines_even`,
      `characters_battle_lines`.`lose`          AS `lines_lose`
    FROM
      `characters`
    LEFT JOIN
      `characters_battle_lines` ON `characters_battle_lines`.`ENo` = `characters`.`ENo`
    WHERE
      `characters`.`ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result    = $statement->execute();
  $character = $statement->fetch();

  if (!$result || !$character) {
    // SQLの実行や実行結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  // 設定しているスキルを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `skill`,
      `lines`
    FROM
      `characters_skills`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }
  
  $setSkills = $statement->fetchAll();

  // 設定可能なスキルを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `skill_id`,
      `name`,
      `cost`,
      `condition`,
      `condition_value`,
      `trigger`,
      `rate_numerator`,
      `rate_denominator`,
      `effects`,
      `type`
    FROM
      `skills_master_data`
    WHERE
      (`required_status` IS NULL) OR
      (`required_status` = 'ATK' AND `required_status_value` <= :ATK) OR
      (`required_status` = 'DEX' AND `required_status_value` <= :DEX) OR
      (`required_status` = 'MND' AND `required_status_value` <= :MND) OR
      (`required_status` = 'AGI' AND `required_status_value` <= :AGI) OR
      (`required_status` = 'DEF' AND `required_status_value` <= :DEF);
  ");
  
  $statement->bindParam(':ATK', $character['ATK']);
  $statement->bindParam(':DEX', $character['DEX']);
  $statement->bindParam(':MND', $character['MND']);
  $statement->bindParam(':AGI', $character['AGI']);
  $statement->bindParam(':DEF', $character['DEF']);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }
  
  $settableSkillDatas = $statement->fetchAll();

  // スキルデータをテキストとして取得しやすいようクラス化
  $settableSkills = array();
  foreach ($settableSkillDatas as $settableSkillData) {
    if ($settableSkillData['type'] === 'active') {
      $settableSkills[$settableSkillData['skill_id']] = new ActiveSkill(
        $settableSkillData['name'],
        $settableSkillData['cost'],
        $settableSkillData['condition'],
        $settableSkillData['condition_value'],
        json_decode($settableSkillData['effects'])
      );
    } else if ($settableSkillData['type'] === 'passive') {
      $settableSkills[$settableSkillData['skill_id']] = new PassiveSkill(
        $settableSkillData['name'],
        $settableSkillData['trigger'],
        $settableSkillData['rate_numerator'],
        $settableSkillData['rate_denominator'],
        json_decode($settableSkillData['effects'])
      );
    }
  }

  // JavaScript上で扱うためにスキル説明をJSON化
  $settableSkillDescriptions = array();
  foreach ($settableSkills as $settableSkillId => $settableSkill) {
    $settableSkillDescriptions[$settableSkillId] = $settableSkill->getDescription();
  }

  $settableSkillDescriptionsJSON = json_encode($settableSkillDescriptions, JSON_UNESCAPED_UNICODE);

  $PAGE_SETTING['TITLE'] = '戦闘設定';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.remaining-np {
  padding: 20px;
  font-weight: bold;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  font-size: 24px;
  color: #666;
}

.remaining-np-error {
  color: #c53f4d;
}

.statuses {
  border-collapse: collapse;
  padding-left: 20px;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  margin-bottom: 20px;
}

.statuses th, .statuses td {
  padding-top: 10px;
  padding-bottom: 10px;
  border-width: 1px 0px;
  border-color: lightgray;
  border-style: solid;
}

.statuses-key th {
  text-align: left;
  padding-left: 20px;
}

.statuses-value td {
  padding-left: 20px;
}

.statuses-input td {
  padding-left: 11px;
}

.statuses-input input {
  margin: 0;
  width: 100px;
}

.statuses-sum td {
  padding-left: 20px;
}

.skill-select-info {
  display: flex;
  align-items: center;
}

.battle-lines th {
  text-align: right;
  padding-right: 10px;
}

.input-lines {
  width: 500px;
}

.skills {
  padding-top: 20px;
}

.skill-number {
  padding: 0 10px;
}

.skill-select {
  margin: 0;
}

.skill-description { 
  min-width: 400px;
  padding: 0 10px;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>戦闘設定</h1>

<form id="form" method="post">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
  <input type="hidden" id="selected-skills" name="selected_skills">
  
  <section>
    <h2>能力値割り振り</h2>

    <section class="form">
      <div class="form-description">
        所持しているNPを能力値に割り振ることができます。<br>
        入力欄に各能力値に割り振りたい値を入力してください。
      </div>
      <div class="remaining-np">
        残りNP:
        <span id="remaining-np-count"><?= $character['NP'] ?></span>
      </div>
      <table class="statuses">
        <tbody>
          <tr class="statuses-key">
            <th>ATK</th>
            <th>DEX</th>
            <th>MND</th>
            <th>AGI</th>
            <th>DEF</th>
          </tr>
          <tr class="statuses-value">
            <td><?=$character['ATK']?></td>
            <td><?=$character['DEX']?></td>
            <td><?=$character['MND']?></td>
            <td><?=$character['AGI']?></td>
            <td><?=$character['DEF']?></td>
          </tr>
          <tr class="statuses-input">
            <td><input id="input-atk" name="atk" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
            <td><input id="input-dex" name="dex" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
            <td><input id="input-mnd" name="mnd" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
            <td><input id="input-agi" name="agi" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
            <td><input id="input-def" name="def" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
          </tr>
          <tr class="statuses-sum">
            <td id="sum-atk">0</td>
            <td id="sum-dex">0</td>
            <td id="sum-mnd">0</td>
            <td id="sum-agi">0</td>
            <td id="sum-def">0</td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>

  <section>
    <h2>スキル設定</h2>

    <section class="form">
      <div class="form-description">
        スキルを設定することができます。アクティブスキルはリストの上から順番に実行判断されます。<br>
        スキルを重複して選択することはできません。
      </div>
      <table class="skills">
      <tbody>
<?php
  for ($i = 0; $i < $GAME_CONFIG['CHARACTER_SKILL_MAX']; $i++) {
    $skillLines = isset($setSkills[$i]) ? $setSkills[$i]['lines'] : '';
    $skillData  = isset($setSkills[$i]) ? $settableSkills[$setSkills[$i]['skill']] : null;
?>
        <tr>
          <th class="skill-number"><?=$i+1?>.</th>
          <td>
            <div class="skill-wrapper">
              <div class="skill-select-info">
                <select id="select-skill-<?=$i+1?>" class="skill-select">
                  <option value="null"<?= !isset($setSkills[$i]) ? ' selected' : '' ?>>-- スキルを選択 --</option>
<?php foreach ($settableSkills as $settableSkillId => $settableSkill) { ?>
                  <option value="<?=$settableSkillId?>"<?= isset($setSkills[$i]) && $settableSkillId == $setSkills[$i]['skill'] ? ' selected' : '' ?>><?=$settableSkillId?>. <?=$settableSkill->name()?></option>
<?php } ?>
                </select>
                <div id="selected-skill-description-<?=$i+1?>" class="skill-description"></div>
              </div>
              <div class="skill-lines">
                <input type="text" id="input-skill-lines-<?=$i+1?>" class="input-lines" value="<?=$skillLines?>" placeholder="セリフ">
              </div>
            </div>
          </td>
        </tr>
<?php
  }
?>
        </tbody>
      </table>
  </section>

  <section>
    <h2>戦闘セリフ設定</h2>

    <table class="battle-lines">
      <tr>
        <th>戦闘開始時</th>
        <td><input type="text" name="lines_start" class="input-lines" value="<?=htmlspecialchars($character['lines_start'])?>"></td>
      </tr>
      <tr>
        <th>回避時</th>
        <td><input type="text" name="lines_dodge" class="input-lines" value="<?=htmlspecialchars($character['lines_dodge'])?>"></td>
      </tr>
      <tr>
        <th>被回避時</th>
        <td><input type="text" name="lines_dodged" class="input-lines" value="<?=htmlspecialchars($character['lines_dodged'])?>"></td>
      </tr>
      <tr>
        <th>被回復時</th>
        <td><input type="text" name="lines_healed" class="input-lines" value="<?=htmlspecialchars($character['lines_healed'])?>"></td>
      </tr>
      <tr>
        <th>自身による回復で回復時</th>
        <td><input type="text" name="lines_healed_own" class="input-lines" value="<?=htmlspecialchars($character['lines_healed_own'])?>"></td>
      </tr>
      <tr>
        <th>通常攻撃時</th>
        <td><input type="text" name="lines_normal_attack" class="input-lines" value="<?=htmlspecialchars($character['lines_normal_attack'])?>"></td>
      </tr>
      <tr>
        <th>敵のHPを0以下にした時</th>
        <td><input type="text" name="lines_defeat" class="input-lines" value="<?=htmlspecialchars($character['lines_defeat'])?>"></td>
      </tr>
      <tr>
        <th>戦闘離脱時</th>
        <td><input type="text" name="lines_killed" class="input-lines" value="<?=htmlspecialchars($character['lines_killed'])?>"></td>
      </tr>
      <tr>
        <th>味方が戦闘離脱時</th>
        <td><input type="text" name="lines_killed_ally" class="input-lines" value="<?=htmlspecialchars($character['lines_killed_ally'])?>"></td>
      </tr>
      <tr>
        <th>攻撃クリティカル時</th>
        <td><input type="text" name="lines_critical" class="input-lines" value="<?=htmlspecialchars($character['lines_critical'])?>"></td>
      </tr>
      <tr>
        <th>被攻撃クリティカル時</th>
        <td><input type="text" name="lines_criticaled" class="input-lines" value="<?=htmlspecialchars($character['lines_criticaled'])?>"></td>
      </tr>
      <tr>
        <th>勝利時</th>
        <td><input type="text" name="lines_win" class="input-lines" value="<?=htmlspecialchars($character['lines_win'])?>"></td>
      </tr>
      <tr>
        <th>引分時</th>
        <td><input type="text" name="lines_even" class="input-lines" value="<?=htmlspecialchars($character['lines_even'])?>"></td>
      </tr>
      <tr>
        <th>敗北時</th>
        <td><input type="text" name="lines_lose" class="input-lines" value="<?=htmlspecialchars($character['lines_lose'])?>"></td>
      </tr>
    </table>
  </section>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button class="button" type="submit">更新</button>
  </div>
</form>

<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）

  var settableSkillDescriptions = <?= $settableSkillDescriptionsJSON ?>; // 選択可能なスキルの説明群

  // エラーメッセージを表示する関数及びその関連処理
  var errorMessageArea = $('#error-message-area');
  function showErrorMessage(message) {
    errorMessageArea.empty();
    errorMessageArea.append(
      '<div class="message-banner message-banner-error">'+
        message +
      '</div>'
    );
  }

  // 現在のステータス、NP
  var currentNP = <?= $character['NP'] ?>;
  var currentStatuses = {
    atk: <?= $character['ATK'] ?>,
    dex: <?= $character['DEX'] ?>,
    mnd: <?= $character['MND'] ?>,
    agi: <?= $character['AGI'] ?>,
    def: <?= $character['DEF'] ?>
  };
  
  // ステータス入力欄
  var inputATK = $('#input-atk');
  var inputDEX = $('#input-dex');
  var inputMND = $('#input-mnd');
  var inputAGI = $('#input-agi');
  var inputDEF = $('#input-def');

  // 残りNP欄
  var remainingAPCount = $('#remaining-np-count');

  // 0以上の整数かどうかを判定するパターン（0埋め不許可）
  var checkPattern = 	/^([1-9][0-9]*|0)$/;

  // 合計量を計算する関数
  // エラーがあれば-1を返す
  function sumInputStatuses() {
    var inputATKValue = inputATK.val();
    var inputDEXValue = inputDEX.val();
    var inputMNDValue = inputMND.val();
    var inputAGIValue = inputAGI.val();
    var inputDEFValue = inputDEF.val();

    // どれかが入力のパターンチェックを通らなければ-1を返す
    if (
      !checkPattern.test(inputATKValue) ||
      !checkPattern.test(inputDEXValue) ||
      !checkPattern.test(inputMNDValue) ||
      !checkPattern.test(inputAGIValue) ||
      !checkPattern.test(inputDEFValue)
    ) {
      return -1;
    }

    // OKなら合計値を返す
    return (
      Number(inputATKValue) +
      Number(inputDEXValue) +
      Number(inputMNDValue) +
      Number(inputAGIValue) +
      Number(inputDEFValue)
    );
  }
  
  // 入力欄に入力イベントがあった際の表示更新処理を設定するための関数
  function setReactiveEventsOnInputStatus(statusNameLower) {
    $('#input-'+statusNameLower).on('change', function() {
      var status = statusNameLower;
      var input = $('#input-'+status).val();

      // 該当の合計欄を更新
      // 入力内容がパターンチェックを通らなければERRと表示
      if (checkPattern.test(input)) {
        $('#sum-'+status).text(currentStatuses[status] + Number(input));
      } else {
        $('#sum-'+status).text('ERR');
      }
      
      // 合計量を取得
      var sum = sumInputStatuses();

      if (sum == -1 || currentNP - sum < 0) {
        // エラーもしくは合計がNPを超えている場合はエラー表示
        remainingAPCount.addClass('remaining-np-error');

        if (sum == -1) {
          // テキストをエラー表示に
          remainingAPCount.text('エラー');
        } else {
          // テキストを超過NP量表示に
          remainingAPCount.text(currentNP - sum);
        }
      } else {
        // そうでなければエラー表示を外し残りNP量を表示
        remainingAPCount.removeClass('remaining-np-error');
        remainingAPCount.text(currentNP - sum);
      }
    });
  }

  // 各ステータスを入力時に表示更新処理をするように設定
  setReactiveEventsOnInputStatus('atk');
  setReactiveEventsOnInputStatus('dex');
  setReactiveEventsOnInputStatus('mnd');
  setReactiveEventsOnInputStatus('agi');
  setReactiveEventsOnInputStatus('def');

  // スキル設定に関する各要素を取得
  var skillSelectors = [];
  for (var i = 0; i < <?=$GAME_CONFIG['CHARACTER_SKILL_MAX']?>; i++) {
    skillSelectors.push($('#select-skill-'+(i+1)));
  }

  var selectedSkillDescriptions = [];
  for (var i = 0; i < <?=$GAME_CONFIG['CHARACTER_SKILL_MAX']?>; i++) {
    selectedSkillDescriptions.push($('#selected-skill-description-'+(i+1)));
  }
  
  var inputSkillLines = [];
  for (var i = 0; i < <?=$GAME_CONFIG['CHARACTER_SKILL_MAX']?>; i++) {
    inputSkillLines.push($('#input-skill-lines-'+(i+1)));
  }

  // スキル設定によって表示を更新する関数
  function updateSkillDescriptions() {
    for (var i = 0; i < <?=$GAME_CONFIG['CHARACTER_SKILL_MAX']?>; i++) {
      var selectedId = skillSelectors[i].val();

      if (selectedId == 'null') {
        selectedSkillDescriptions[i].html('----');
      } else {
        selectedSkillDescriptions[i].html('【' + settableSkillDescriptions[selectedId].cond + '】' + settableSkillDescriptions[selectedId].desc);
      }
    }
  }

  // 初期の設定を表示するため表示更新関数を呼び出し
  updateSkillDescriptions();

  // スキル設定を変更時に表示を更新する関数を呼び出しするよう設定
  skillSelectors.forEach(function(selector) {
    selector.on('change', updateSkillDescriptions);
  });

  $('#form').submit(function(){
    var sum = sumInputStatuses(); // 入力の合計値を取得

    // 入力値検証
    // ステータス割り振りの入力欄が不正な場合はエラーメッセージを表示して送信を中断
    if (sum == -1) {
      showErrorMessage('ステータス割り振りの入力欄が不正です');
      return false;
    }

    // ステータスの割り振り量が残りNPを超過している場合はエラーメッセージを表示して送信を中断
    if (currentNP - sum < 0) {
      showErrorMessage('ステータスの割り振り量が残りNPを超過しています');
      return false;
    }

    // スキルの重複チェック
    var skillDuplicatedChecker = [];
    for (var i = 0; i < <?=$GAME_CONFIG['CHARACTER_SKILL_MAX']?>; i++) {
      var selectedId = skillSelectors[i].val();

      if (selectedId == 'null') {
        // nullであればチェックをスキップ
        continue;
      } else if (!skillDuplicatedChecker[selectedId]) {
        // skillDuplicatedCheckerの該当のスキルID項目がまだtrueでないならtrueに
        skillDuplicatedChecker[selectedId] = true;
      } else if (skillDuplicatedChecker[selectedId]) {
        // skillDuplicatedCheckerの該当のスキルID項目がすでにtrueなら重複したスキルが設定されているため、エラーメッセージを表示して送信を中断
        showErrorMessage('スキルが重複しています');
        return false;
      }
    }

    // レスポンス待ちの場合アラートを表示して送信を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 送信

    // 設定されたスキルとセリフを取得
    var selectedSkills = [];

    for (var i = 0; i < <?=$GAME_CONFIG['CHARACTER_SKILL_MAX']?>; i++) {
      var selectedId = skillSelectors[i].val();
      
      if (selectedId == 'null') {
        continue; // 選択されていないスキルは送信対象としない
      } else {
        selectedSkills.push({
          skill: Number(selectedId),
          lines: inputSkillLines[i].val()
        });
      }
    }

    // スキル設定をJSON化された配列として#selected-skillsに設定
    $('#selected-skills').val(JSON.stringify(selectedSkills));

    // 上記のどれにも当てはまらない場合送信が行われるためレスポンス待ちをONに
    waitingResponse = true;
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>