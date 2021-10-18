<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/parser.php';

  // DBから値を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `ENo`,
      `name`,
      `AP`,
      `ATK`,
      `DEX`,
      `MND`,
      `AGI`,
      `DEF`,
      `profile`,
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
    http_response_code(500); 
    exit;
  }

  $icons = parseIconsResult($data['icons']);

  $PAGE_SETTING['TITLE'] = 'ホーム';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<h1>ENo.<?=$data['ENo']?> <?=htmlspecialchars($data['name'])?></h1>

<?php
  $COMPONENT_CHARACTER_PROFILE['type']           = 'home';
  $COMPONENT_CHARACTER_PROFILE['profile_images'] = array_filter(explode("\n", $data['profile_images']), "strlen");
  $COMPONENT_CHARACTER_PROFILE['AP']             = $data['AP'];
  $COMPONENT_CHARACTER_PROFILE['ATK']            = $data['ATK'];
  $COMPONENT_CHARACTER_PROFILE['DEX']            = $data['DEX'];
  $COMPONENT_CHARACTER_PROFILE['MND']            = $data['MND'];
  $COMPONENT_CHARACTER_PROFILE['AGI']            = $data['AGI'];
  $COMPONENT_CHARACTER_PROFILE['DEF']            = $data['DEF'];
  $COMPONENT_CHARACTER_PROFILE['tags']           = array_filter(explode(' ', $data['tags']), "strlen");
  $COMPONENT_CHARACTER_PROFILE['profile']        = $data['profile'];
  $COMPONENT_CHARACTER_PROFILE['icons']          = parseIconsResult($data['icons']);

  include GETENV('GAME_ROOT').'/components/character_profile.php';
?>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>