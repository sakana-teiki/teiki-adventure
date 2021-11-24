<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/parser.php';

  require_once GETENV('GAME_ROOT').'/battle/skills/bases.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/conditions.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/effect-conditions.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/elements.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/targets.php';

  // プロフィールデータを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `ENo`,
      `name`,
      `consumedAP`,
      `ATK`,
      `DEX`,
      `MND`,
      `AGI`,
      `DEF`,
      `profile`,
      (SELECT `AP` FROM `game_status`) AS `distributedAP`,
      (SELECT GROUP_CONCAT(`url`               SEPARATOR '\n') FROM `characters_profile_images` WHERE `ENo` = :ENo GROUP BY `ENo`) AS `profile_images`,
      (SELECT GROUP_CONCAT(`tag`               SEPARATOR ' ')  FROM `characters_tags`           WHERE `ENo` = :ENo GROUP BY `ENo`) AS `tags`,
      (SELECT GROUP_CONCAT(`name`, '\n', `url` SEPARATOR '\n') FROM `characters_icons`          WHERE `ENo` = :ENo GROUP BY `ENo`) AS `icons`
    FROM
      `characters`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();
  $data   = $statement->fetch();

  if (!$result || !$data) {
    // SQLの実行や実行結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  $icons = parseIconsResult($data['icons']);

  // 設定しているスキルを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `skills_master_data`.`name`,
      `skills_master_data`.`cost`,
      `skills_master_data`.`condition`,
      `skills_master_data`.`condition_value`,
      `skills_master_data`.`trigger`,
      `skills_master_data`.`rate_numerator`,
      `skills_master_data`.`rate_denominator`,
      `skills_master_data`.`effects`,
      `skills_master_data`.`type`
    FROM
      `characters_skills`
    JOIN
      `skills_master_data` ON `skills_master_data`.`skill_id` = `characters_skills`.`skill`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }
  
  $skillDatas = $statement->fetchAll();

  // スキルデータをテキストとして取得しやすいようクラス化
  $skills = array();
  foreach ($skillDatas as $skillData) {
    if ($skillData['type'] === 'active') {
      $skills[] = new ActiveSkill(
        $skillData['name'],
        $skillData['cost'],
        $skillData['condition'],
        $skillData['condition_value'],
        json_decode($skillData['effects'])
      );
    } else if ($skillData['type'] === 'passive') {
      $skills[] = new PassiveSkill(
        $skillData['name'],
        $skillData['trigger'],
        $skillData['rate_numerator'],
        $skillData['rate_denominator'],
        json_decode($skillData['effects'])
      );
    }
  }

  // 定期更新のログが存在している回を取得（宣言が行われており、ゲームステータスのnext_update_nth未満のものはログが存在しているのでそれを対象とする）
  $statement = $GAME_PDO->prepare("
    SELECT
      `nth`
    FROM
      `characters_declarations`
    WHERE
      `ENo` = :ENo AND
      `nth` < (SELECT `next_update_nth` FROM `game_status`);
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }
  
  $declarations = $statement->fetchAll();


  $PAGE_SETTING['TITLE'] = 'ホーム';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>ENo.<?=$data['ENo']?> <?=htmlspecialchars($data['name'])?></h1>

<?php
  $COMPONENT_CHARACTER_PROFILE['type']           = 'home';
  $COMPONENT_CHARACTER_PROFILE['ENo']            = $_SESSION['ENo'];
  $COMPONENT_CHARACTER_PROFILE['profile_images'] = array_filter(explode("\n", $data['profile_images']), "strlen");
  $COMPONENT_CHARACTER_PROFILE['AP']             = $data['distributedAP'] - $data['consumedAP'];
  $COMPONENT_CHARACTER_PROFILE['ATK']            = $data['ATK'];
  $COMPONENT_CHARACTER_PROFILE['DEX']            = $data['DEX'];
  $COMPONENT_CHARACTER_PROFILE['MND']            = $data['MND'];
  $COMPONENT_CHARACTER_PROFILE['AGI']            = $data['AGI'];
  $COMPONENT_CHARACTER_PROFILE['DEF']            = $data['DEF'];
  $COMPONENT_CHARACTER_PROFILE['tags']           = array_filter(explode(' ', $data['tags']), "strlen");
  $COMPONENT_CHARACTER_PROFILE['profile']        = $data['profile'];
  $COMPONENT_CHARACTER_PROFILE['icons']          = parseIconsResult($data['icons']);
  $COMPONENT_CHARACTER_PROFILE['skills']         = $skills;
  $COMPONENT_CHARACTER_PROFILE['declarations']   = $declarations;

  include GETENV('GAME_ROOT').'/components/character_profile.php';
?>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>