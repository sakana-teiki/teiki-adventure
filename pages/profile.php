<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';
  require_once GETENV('GAME_ROOT').'/utils/notification.php';

  require_once GETENV('GAME_ROOT').'/battle/skills/bases.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/conditions.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/effect-conditions.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/elements.php';
  require_once GETENV('GAME_ROOT').'/battle/skills/targets.php';

  // GET以外の場合のみ認証を必要とする
  if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    require GETENV('GAME_ROOT').'/middlewares/verification.php';
  }

  require_once GETENV('GAME_ROOT').'/utils/parser.php';

  // 入力値検証＆対象の取得
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // POSTの場合
    if (!validatePOST('target', ['non-empty', 'natural-number'])) {
      responseError(400);
    }

    $target = $_POST['target']; // targetの値を対象に指定
  } else {
    // GETの場合
    if (!validateGET('ENo', ['non-empty', 'natural-number'])) {
      responseError(400);
    }

    $target = $_GET['ENo']; // URLパラメータのENoを対象に指定
  }

  // ログインしている場合、お気に入りしているか/ミュートしているか/ブロックしているか/ブロックされているかを取得
  if ($GAME_LOGGEDIN) {
    $statement = $GAME_PDO->prepare("
      SELECT
        IFNULL((SELECT true FROM `characters_favs`   WHERE `faver`   = :user AND `faved`   = :target), false) AS `fav`,
        IFNULL((SELECT true FROM `characters_mutes`  WHERE `muter`   = :user AND `muted`   = :target), false) AS `mute`,
        IFNULL((SELECT true FROM `characters_blocks` WHERE `blocker` = :user AND `blocked` = :target), false) AS `block`,
        IFNULL((SELECT true FROM `characters_blocks` WHERE `blocked` = :user AND `blocker` = :target), false) AS `blocked`;
    ");

    $statement->bindParam(':user',   $_SESSION['ENo']);
    $statement->bindParam(':target', $target);
  
    $result   = $statement->execute();
    $relation = $statement->fetch();
  
    if (!$result || !$relation) {
      // SQLの実行に失敗した場合あるいは結果が存在しない場合は500(Internal Server Error)を返し処理を中断
      responseError(500);
    }
  }

  // DBから対象キャラクターの値を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `ENo`,
      `name`,
      `nickname`,
      `ATK`,
      `DEX`,
      `MND`,
      `AGI`,
      `DEF`,
      `profile`,
      `webhook`,
      (SELECT GROUP_CONCAT(`url`               SEPARATOR '\n') FROM `characters_profile_images` WHERE `ENo` = :ENo GROUP BY `ENo`) AS `profile_images`,
      (SELECT GROUP_CONCAT(`tag`               SEPARATOR ' ')  FROM `characters_tags`           WHERE `ENo` = :ENo GROUP BY `ENo`) AS `tags`,
      (SELECT GROUP_CONCAT(`name`, '\n', `url` SEPARATOR '\n') FROM `characters_icons`          WHERE `ENo` = :ENo GROUP BY `ENo`) AS `icons`,
      `notification_faved`,
      `notification_webhook_faved`
    FROM
      `characters`
    WHERE
      `ENo`     = :ENo AND
      `deleted` = false;
  ");

  $statement->bindParam(':ENo', $target);

  $result = $statement->execute();
  $data   = $statement->fetch();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  if (!$data) {
    // 実行結果の取得に失敗した場合は404(Not Found)を返し処理を中断
    responseError(404);
  }
  
  // POSTの場合各アクションを行う
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 自分自身をお気に入り、ミュートetc..しようとしていた場合400(Bad Request)を返し処理を中断
    if ($target == $_SESSION['ENo']) {
      responseError(400);
    }

    switch ($_POST['action']) {
      case "fav":
        // fav:ブロックしている/されている状態でなければcharacters_favsに対象を追加する
        if (!$relation['block'] && !$relation['blocked']) {
          $statement = $GAME_PDO->prepare("
            INSERT INTO `characters_favs` (
              `faver`, `faved`
            ) VALUES (
              :user, :target
            );
          ");

          $statement->bindParam(':user',   $_SESSION['ENo']);
          $statement->bindParam(':target', $target);

          $result = $statement->execute();
        
          if (!$result) {
            responseError(500);
          }

          $relation['fav'] = true;

          // お気に入り通知が有効ならお気に入りされました通知を作成
          if ($data['notification_faved']) {
            $statement = $GAME_PDO->prepare("
              INSERT INTO `notifications` (
                `ENo`,
                `type`,
                `target`,
                `message`
              ) VALUES (
                :ENo,
                'faved',
                :target,
                ''
              );
            ");

            $statement->bindParam(':ENo',    $target);
            $statement->bindParam(':target', $_SESSION['ENo']);

            $result = $statement->execute();
          
            if (!$result) {
              responseError(500);
            }
          }

          // Webhookが入力されており、Discordお気に入り通知が有効なら通知を送信
          if ($data['webhook'] && $data['notification_webhook_faved']) {
            // お気に入りを行ったキャラクター（ユーザー）の情報を取得
            $statement = $GAME_PDO->prepare("
              SELECT
                `ENo`,
                `nickname`
              FROM
                `characters`
              WHERE
                `ENo` = :user;
            ");

            $statement->bindParam(':user', $_SESSION['ENo']);

            $result    = $statement->execute();
            $faverInfo = $statement->fetch();
          
            if ($result && $faverInfo) {
              notifyDiscord($data['webhook'], 'ENo.'.$faverInfo['ENo'].' '.$faverInfo['nickname'].'にお気に入りされました。 '.$GAME_CONFIG['ABSOLUTE_URI'].'profile?ENo='.$faverInfo['ENo']);
            }
          }
        }

        break;
      case "unfav":
        // unfav:characters_favsから対象を削除する
        $statement = $GAME_PDO->prepare("
          DELETE FROM
            `characters_favs`
          WHERE
            `faver` = :user AND `faved` = :target;
        ");

        $statement->bindParam(':user',   $_SESSION['ENo']);
        $statement->bindParam(':target', $target);

        $result = $statement->execute();
        
        if (!$result) {
          responseError(500);
        }

        $relation['fav'] = false;

        break;
      case "mute":
        // mute:characters_mutesに対象を追加する
        $statement = $GAME_PDO->prepare("
          INSERT INTO `characters_mutes` (
            `muter`, `muted`
          ) VALUES (
            :user, :target
          );
        ");

        $statement->bindParam(':user',   $_SESSION['ENo']);
        $statement->bindParam(':target', $target);

        $result = $statement->execute();
        
        if (!$result) {
          responseError(500);
        }

        $relation['mute'] = true;

        break;
      case "unmute":
        // unmute:characters_mutesから対象を削除する
        $statement = $GAME_PDO->prepare("
          DELETE FROM
            `characters_mutes`
          WHERE
            `muter` = :user AND `muted` = :target;
        ");

        $statement->bindParam(':user',   $_SESSION['ENo']);
        $statement->bindParam(':target', $target);

        $result = $statement->execute();
        
        if (!$result) {
          responseError(500);
        }

        $relation['mute'] = false;

        break;
      case "block":
        // block:characters_favsからお互いを削除し、characters_blocksに対象を追加する
        $GAME_PDO->beginTransaction();

        $statement = $GAME_PDO->prepare("
          DELETE FROM
            `characters_favs`
          WHERE
            (`faver` = :user AND `faved` = :target) OR
            (`faved` = :user AND `faver` = :target);
        ");

        $statement->bindParam(':user',   $_SESSION['ENo']);
        $statement->bindParam(':target', $target);

        $result = $statement->execute();
        
        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500);
        }
        
        $statement = $GAME_PDO->prepare("
          INSERT INTO `characters_blocks` (
            `blocker`, `blocked`
          ) VALUES (
            :user, :target
          );
        ");

        $statement->bindParam(':user',   $_SESSION['ENo']);
        $statement->bindParam(':target', $target);

        $result = $statement->execute();
        
        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500);
        }

        $GAME_PDO->commit();

        $relation['fav']   = false;
        $relation['block'] = true;

        break;
      case "unblock":
        // unblock:characters_blocksから対象を削除する
        $statement = $GAME_PDO->prepare("
          DELETE FROM
            `characters_blocks`
          WHERE
            `blocker` = :user AND `blocked` = :target;
        ");

        $statement->bindParam(':user',   $_SESSION['ENo']);
        $statement->bindParam(':target', $target);

        $result = $statement->execute();
        
        if (!$result) {
          responseError(500);
        }

        $relation['block'] = false;
        
        break;
      default:
        // actionが以上のどれでもない場合は400を返して処理を中断
        responseError(400);
    }
  }

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

  $statement->bindParam(':ENo', $target);

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

  $statement->bindParam(':ENo', $target);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }
  
  $declarations = $statement->fetchAll();

  $PAGE_SETTING['TITLE'] = 'ENo.'.$data['ENo'].' '.$data['name'];

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.profile-relation {
  display: flex;
  justify-content: flex-end;
  width: 100%;
  margin-bottom: 10px;
}

.profile-relation-button {
  display: inline-flex;
  justify-content: center;
  padding: 4px 10px;
  border: 1px solid #666;
  border-radius: 4px;
  margin: 0 5px;
  text-decoration: none;
  font-weight: bold;
  color: #666;
  cursor: pointer;
}

.profile-relation-button-done {
  border: 1px solid #333;
  background: #666;
  color: #EEE;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>ENo.<?=$data['ENo']?> <?=htmlspecialchars($data['name'])?></h1>
<?php if ($GAME_LOGGEDIN && $data['ENo'] != $_SESSION['ENo']) { // ログインしており、表示しているのが自分のキャラクターではない場合 ?>
<section class="profile-relation">
<?php if (!$relation['block'] && !$relation['blocked']) { // ブロックしている/されている状態でなければ ?>
  <?php if (!$relation['fav']) { ?>
  <form method="post">
    <input  type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input  type="hidden" name="target" value="<?=$data['ENo']?>">
    <button type="submit" name="action" value="fav" class="profile-relation-button">お気に入りする</button>
  <?php } else { ?>
  <form method="post">
    <input  type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input  type="hidden" name="target" value="<?=$data['ENo']?>">
    <button type="submit" name="action" value="unfav" class="profile-relation-button profile-relation-button-done">お気に入り中</button>
  </form>
  <?php } ?>
<?php } ?>
  <?php if (!$relation['mute']) { ?>
  <form method="post">
    <input  type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input  type="hidden" name="target" value="<?=$data['ENo']?>">
    <button type="submit" name="action" value="mute" class="profile-relation-button">ミュートする</button>
  </form>
  <?php } else { ?>
  <form method="post">
    <input  type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input  type="hidden" name="target" value="<?=$data['ENo']?>">
    <button type="submit" name="action" value="unmute" class="profile-relation-button profile-relation-button-done">ミュート中</button>
  </form>
  <?php } ?>
  <?php if (!$relation['block']) { ?>
  <form method="post">
    <input  type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input  type="hidden" name="target" value="<?=$data['ENo']?>">
    <button type="submit" name="action" value="block" class="profile-relation-button">ブロックする</button>
  </form>
  <?php } else { ?>
  <form method="post">
    <input  type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input  type="hidden" name="target" value="<?=$data['ENo']?>">
    <button type="submit" name="action" value="unblock" class="profile-relation-button profile-relation-button-done">ブロック中</button>
  </form>
  <?php } ?>
</section>
<?php } ?>

<?php
// ブロックされていない場合にのみプロフィールを表示
if (!isset($relation) || !$relation['blocked']) {
  $COMPONENT_CHARACTER_PROFILE['type']           = 'profile';
  $COMPONENT_CHARACTER_PROFILE['ENo']            = $data['ENo'];
  $COMPONENT_CHARACTER_PROFILE['profile_images'] = array_filter(explode("\n", $data['profile_images']), "strlen");
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
} else {
?>
  <p>ブロックされているため、プロフィールを表示できません。</p>
<?php
  }
?>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>