<?php
  putenv('GAME_ROOT='.dirname(__DIR__));

  // .htaccessでSetEnvが使えず$_SERVER['DOCUMENT_ROOT']を使用するよう変更した場合用に、コマンドライン等からDOCUMENT_ROOTを設定できるように
  // GAME_ROOTをDOCUMENT_ROOTを使用する形に置換していないのであれば特に気にする必要はありません
  // 書式：php filename.php --document-root="path"
  if (isset($argv)) {
    foreach ($argv as $arg) {
      if (strpos($arg, '--document-root=') === 0) {
        $_SERVER['DOCUMENT_ROOT'] = substr($arg, strlen('--document-root='));
      }
    }
  }
  
  require_once GETENV('GAME_ROOT').'/configs/environment.php';
  require_once GETENV('GAME_ROOT').'/configs/general.php';
  
  require_once GETENV('GAME_ROOT').'/battle/battle.php';
  
  $GAME_PDO = new PDO('mysql:dbname='.$GAME_CONFIG['MYSQL_DBNAME'].';host='.$GAME_CONFIG['MYSQL_HOST'].':'.$GAME_CONFIG['MYSQL_PORT'], $GAME_CONFIG['MYSQL_USERNAME'], $GAME_CONFIG['MYSQL_PASSWORD']);

  // ゲームステータスの取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `update_status`,
      `next_update_nth`
    FROM
      `game_status`;
  ");

  $result     = $statement->execute();
  $gameStatus = $statement->fetch();

  if (!$result || !$gameStatus) {
    echo 'ゲームステータスの取得に失敗しました。処理を終了します。';
    exit;
  }

  // コンソールからの実行であれば確認処理を行う
  if (php_sapi_name() == 'cli') {
    if ($gameStatus['update_status']) {
      echo '本当に更新を行いますか？更新を行う場合yesと入力してください。: ';
    } else {
      echo '本当に更新を行いますか？更新が確定されていないため、再更新となります。再更新を行う場合yesと入力してください。: ';
    }
    $input = trim(fgets(STDIN));
    
    if ($input != 'yes') {
      echo '更新が中止されました。';
      exit;
    }
  }

  // 更新確定状態なら次回の更新回数が、更新未確定状態なら今回の更新回数が更新対象の回数となる
  $target_nth = $gameStatus['update_status'] ? $gameStatus['next_update_nth'] : $gameStatus['next_update_nth'] - 1;

  // キャラクターの行動宣言の取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `characters_declarations`.`ENo`,
      `characters_declarations`.`diary`
    FROM
      `characters_declarations`
    JOIN
      `characters` ON `characters`.`ENo` = `characters_declarations`.`ENo`
    WHERE
      `characters_declarations`.`nth` = :nth AND
      `characters`.`deleted` = false;
  ");

  $statement->bindParam(':nth', $target_nth, PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    echo 'キャラクターの行動宣言の取得に失敗しました。処理を終了します。';
    exit;
  }

  $declarations = $statement->fetchAll();

  // トランザクション開始
  $GAME_PDO->beginTransaction();

  // 再更新でないなら現時点の各種宣言状況・ステータス等を保管
  if ($gameStatus['update_status']) {

    // 現時点のステータスを記録
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters_declarations`
      SET
        `characters_declarations`.`name` = (SELECT `nickname` FROM `characters` WHERE `characters`.`ENo` = `characters_declarations`.`ENo`),
        `characters_declarations`.`ATK`  = (SELECT `ATK`      FROM `characters` WHERE `characters`.`ENo` = `characters_declarations`.`ENo`),
        `characters_declarations`.`DEX`  = (SELECT `DEX`      FROM `characters` WHERE `characters`.`ENo` = `characters_declarations`.`ENo`),
        `characters_declarations`.`MND`  = (SELECT `MND`      FROM `characters` WHERE `characters`.`ENo` = `characters_declarations`.`ENo`),
        `characters_declarations`.`AGI`  = (SELECT `AGI`      FROM `characters` WHERE `characters`.`ENo` = `characters_declarations`.`ENo`),
        `characters_declarations`.`DEF`  = (SELECT `DEF`      FROM `characters` WHERE `characters`.`ENo` = `characters_declarations`.`ENo`)
      WHERE
        `nth` = :nth;
    ");

    $statement->bindParam(':nth', $target_nth);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      echo 'ステータスの記録に失敗しました。処理を終了します。';
      exit;
    }

    // 現時点のアイコンを記録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `characters_declarations_icons` (
        `ENo`,
        `nth`,
        `name`,
        `url`
      )

      SELECT
        `characters_declarations`.`ENo`,
        `characters_declarations`.`nth`,
        `characters_icons`.`name`,
        `characters_icons`.`url`
      FROM
        `characters_declarations`
      JOIN
        `characters_icons` ON `characters_icons`.`ENo` = `characters_declarations`.`ENo`
      WHERE
        `nth` = :nth;
    ");

    $statement->bindParam(':nth', $target_nth);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      echo 'アイコンの記録に失敗しました。処理を終了します。';
      exit;
    }

    // 現時点の戦闘セリフを記録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `characters_declarations_battle_lines` (
        `ENo`,
        `nth`,
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
      )

      SELECT
        `characters_declarations`.`ENo`,
        `characters_declarations`.`nth`,
        `characters_battle_lines`.`start`,
        `characters_battle_lines`.`dodge`,
        `characters_battle_lines`.`dodged`,
        `characters_battle_lines`.`healed`,
        `characters_battle_lines`.`healed_own`,
        `characters_battle_lines`.`normal_attack`,
        `characters_battle_lines`.`defeat`,
        `characters_battle_lines`.`killed`,
        `characters_battle_lines`.`killed_ally`,
        `characters_battle_lines`.`critical`,
        `characters_battle_lines`.`criticaled`,
        `characters_battle_lines`.`win`,
        `characters_battle_lines`.`even`,
        `characters_battle_lines`.`lose`
      FROM
        `characters_declarations`
      JOIN
        `characters_battle_lines` ON `characters_battle_lines`.`ENo` = `characters_declarations`.`ENo`
      WHERE
        `nth` = :nth;
    ");

    $statement->bindParam(':nth', $target_nth);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      echo '戦闘セリフの記録に失敗しました。処理を終了します。';
      exit;
    }

    // 現時点のスキルを記録
    $statement = $GAME_PDO->prepare("
      INSERT INTO `characters_declarations_skills` (
        `ENo`,
        `nth`,
        `skill`,
        `lines`
      )

      SELECT
        `characters_declarations`.`ENo`,
        `characters_declarations`.`nth`,
        `characters_skills`.`skill`,
        `characters_skills`.`lines`
      FROM
        `characters_declarations`
      JOIN
        `characters_skills` ON `characters_skills`.`ENo` = `characters_declarations`.`ENo`
      WHERE
        `nth` = :nth;
    ");

    $statement->bindParam(':nth', $target_nth);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      echo 'スキルの記録に失敗しました。処理を終了します。';
      exit;
    }
  }

  // 該当の更新回のステージデータを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `stage_id`,
      `title`,
      `pre_text`,
      `post_text`
    FROM
      `story_stages_master_data`
    WHERE
      `nth` = :nth;
  ");

  $statement->bindParam(':nth', $target_nth);

  $result = $statement->execute();
  $stage  = $statement->fetch();

  if (!$result || !$stage) {
    $GAME_PDO->rollBack();
    echo 'ステージの取得に失敗しました。処理を終了します。';
    exit;
  }

  // ステージIDを取得
  $stageId = $stage['stage_id'];

  // ステージに出現する敵を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `enemy`
    FROM
      `story_stages_master_data_enemies`
    WHERE
      `stage` = :stage;
  ");

  $statement->bindParam(':stage', $stageId, PDO::PARAM_INT);

  $result      = $statement->execute();
  $enemieDatas = $statement->fetchAll();

  if (!$result || !$enemieDatas) {
    $GAME_PDO->rollBack();
    echo 'ステージに出現する敵の取得に失敗しました。処理を終了します。';
    exit;
  }

  $enemies = [];
  foreach ($enemieDatas as $enemyData) {
    // 敵のステータスと戦闘時セリフを取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `enemies_master_data`.`enemy_id`,
        `enemies_master_data`.`name`,
        `enemies_master_data`.`ATK`,
        `enemies_master_data`.`DEX`,
        `enemies_master_data`.`MND`,
        `enemies_master_data`.`AGI`,
        `enemies_master_data`.`DEF`,
        IFNULL(`enemies_master_data_battle_lines`.`start`, '')         AS `lines_start`,
        IFNULL(`enemies_master_data_battle_lines`.`dodge`, '')         AS `lines_dodge`,
        IFNULL(`enemies_master_data_battle_lines`.`dodged`, '')        AS `lines_dodged`,
        IFNULL(`enemies_master_data_battle_lines`.`healed`, '')        AS `lines_healed`,
        IFNULL(`enemies_master_data_battle_lines`.`healed_own`, '')    AS `lines_healed_own`,
        IFNULL(`enemies_master_data_battle_lines`.`normal_attack`, '') AS `lines_normal_attack`,
        IFNULL(`enemies_master_data_battle_lines`.`defeat`, '')        AS `lines_defeat`,
        IFNULL(`enemies_master_data_battle_lines`.`killed`, '')        AS `lines_killed`,
        IFNULL(`enemies_master_data_battle_lines`.`killed_ally`, '')   AS `lines_killed_ally`,
        IFNULL(`enemies_master_data_battle_lines`.`critical`, '')      AS `lines_critical`,
        IFNULL(`enemies_master_data_battle_lines`.`criticaled`, '')    AS `lines_criticaled`,
        IFNULL(`enemies_master_data_battle_lines`.`win`, '')           AS `lines_win`,
        IFNULL(`enemies_master_data_battle_lines`.`even`, '')          AS `lines_even`,
        IFNULL(`enemies_master_data_battle_lines`.`lose`, '')          AS `lines_lose`
      FROM
        `enemies_master_data`
      LEFT JOIN
        `enemies_master_data_battle_lines` ON `enemies_master_data_battle_lines`.`enemy` = `enemies_master_data`.`enemy_id`
      WHERE
        `enemies_master_data`.`enemy_id` = :enemy;
    ");

    $statement->bindParam(':enemy', $enemyData['enemy'], PDO::PARAM_INT);

    $result = $statement->execute();
    $enemy  = $statement->fetch();

    if (!$result || !$enemy) {
      $GAME_PDO->rollBack();
      echo '敵のステータス/戦闘時セリフの取得に失敗しました。処理を終了します。';
      exit;
    }

    // 戦闘セリフは1つのキーとしてまとめる
    $enemy['battleLines'] = array(
      'start'         => $enemy['lines_start'],
      'dodge'         => $enemy['lines_dodge'],
      'dodged'        => $enemy['lines_dodged'],
      'healed'        => $enemy['lines_healed'],
      'healed_own'    => $enemy['lines_healed_own'],
      'normal_attack' => $enemy['lines_normal_attack'],
      'defeat'        => $enemy['lines_defeat'],
      'killed'        => $enemy['lines_killed'],
      'killed_ally'   => $enemy['lines_killed_ally'],
      'critical'      => $enemy['lines_critical'],
      'criticaled'    => $enemy['lines_criticaled'],
      'win'           => $enemy['lines_win'],
      'even'          => $enemy['lines_even'],
      'lose'          => $enemy['lines_lose']
    );

    // 敵のアイコン情報を取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `name`,
        `url`
      FROM
        `enemies_master_data_icons`
      WHERE
        `enemy` = :enemy;
    ");

    $statement->bindParam(':enemy', $enemyData['enemy'], PDO::PARAM_INT);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    $enemy['icons'] = $statement->fetchAll();

    // 敵のスキル情報を取得
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
        `skills_master_data`.`type`,
        `enemies_master_data_skills`.`lines`
      FROM
        `enemies_master_data_skills`
      JOIN
        `skills_master_data` ON `skills_master_data`.`skill_id` = `enemies_master_data_skills`.`skill`
      WHERE
        `enemies_master_data_skills`.`enemy` = :enemy;
    ");

    $statement->bindParam(':enemy', $enemyData['enemy'], PDO::PARAM_INT);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      echo '敵のスキル情報の取得に失敗しました。処理を終了します。';
      exit;
    }

    $enemy['skills'] = $statement->fetchAll();

    // スキル効果群のJSONをパースして置き換え
    $cnt = count($enemy['skills']);
      
    for ($i = 0; $i < $cnt; $i++) {
      $enemy['skills'][$i]['effects'] = json_decode($enemy['skills'][$i]['effects']);
    }

    $enemies[] = $enemy;
  }

  // 重複している敵の識別子付与
  // 例えば2体いるスライムに対してスライムA、スライムBというように識別子を付与する

  // 敵の種類ごとに出現する数をカウント
  $repeatedEnemyChecker = array(); // 総出現回数カウント用
  foreach ($enemies as $enemy) {
    if (isset($repeatedEnemyChecker[$enemy['enemy_id']])) {
      $repeatedEnemyChecker[$enemy['enemy_id']]++;
    } else {
      $repeatedEnemyChecker[$enemy['enemy_id']] = 1;
    }
  }

  // 2体以上登場していた敵に対して識別子を付与 値を書き換えるのでforでループ
  $repeatedEnemyCounter = array(); // ループ中の出現回数カウント用
  $cnt = count($enemies);
  for ($i = 0; $i < $cnt; $i++) {
    if (isset($repeatedEnemyCounter[$enemies[$i]['enemy_id']])) {
      $repeatedEnemyCounter[$enemies[$i]['enemy_id']]++;
    } else {
      $repeatedEnemyCounter[$enemies[$i]['enemy_id']] = 1;
    }

    if (2 <= $repeatedEnemyChecker[$enemies[$i]['enemy_id']]) {
      $alphabets = range('A', 'Z');
      $enemies[$i]['name'] .= $alphabets[$repeatedEnemyCounter[$enemies[$i]['enemy_id']] - 1];
    }
  }

  // 行動宣言ごとに処理
  foreach ($declarations as $declaration) {
    // 行動内容に応じた結果の生成
    // メンバーの取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `member`
      FROM
        `characters_declarations_members`
      WHERE
        `ENo` = :ENo AND
        `nth` = :nth;
    ");

    $statement->bindParam(':ENo', $declaration['ENo'], PDO::PARAM_INT);
    $statement->bindParam(':nth', $target_nth,         PDO::PARAM_INT);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      echo 'メンバーの取得に失敗しました。処理を終了します。';
      exit;
    }

    $memberENoResults = $statement->fetchAll();

    // 連れ出し主を含めたメンバー配列の生成
    $memberENos = [intval($declaration['ENo'])];
    foreach ($memberENoResults as $memberENoResult) {
      $memberENos[] = intval($memberENoResult['member']);
    }

    // メンバー情報の取得
    $members = [];
    foreach ($memberENos as $memberENo) {
      // メンバーとなるキャラクターのステータスを取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `name`,
          `ATK`,
          `DEX`,
          `MND`,
          `AGI`,
          `DEF`
        FROM
          `characters_declarations`
        WHERE
          `ENo` = :ENo AND
          `nth` = :nth;
      ");
  
      $statement->bindParam(':ENo', $memberENo,  PDO::PARAM_INT);
      $statement->bindParam(':nth', $target_nth, PDO::PARAM_INT);
  
      $result = $statement->execute();
  
      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
  
      $member = $statement->fetch();
  
      if (!$member) {
        $GAME_PDO->rollBack();
        responseError(404); // キャラクターの取得に失敗した場合は404(Not Found)を返して処理を中断
      }

      // メンバーとなるキャラクターの戦闘セリフを取得
      $statement = $GAME_PDO->prepare("
        SELECT
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
        FROM
          `characters_declarations_battle_lines`
        WHERE
          `ENo` = :ENo AND
          `nth` = :nth;
      ");
  
      $statement->bindParam(':ENo', $memberENo,  PDO::PARAM_INT);
      $statement->bindParam(':nth', $target_nth, PDO::PARAM_INT);
  
      $result = $statement->execute();
  
      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
  
      $battleLines = $statement->fetch();
  
      if ($battleLines) {
        // 戦闘セリフの設定があればそれを$memberに設定
        $member['battleLines'] = $battleLines;
      } else {
        // なければそれぞれ空文字列を設定
        $member['battleLines'] = array(
          'start'         => '',
          'dodge'         => '',
          'dodged'        => '',
          'healed'        => '',
          'healed_own'    => '',
          'normal_attack' => '',
          'defeat'        => '',
          'killed'        => '',
          'killed_ally'   => '',
          'critical'      => '',
          'criticaled'    => '',
          'win'           => '',
          'even'          => '',
          'lose'          => ''
        );
      }

      // メンバーとなるキャラクターのアイコン情報を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `name`,
          `url`
        FROM
          `characters_declarations_icons`
        WHERE
          `ENo` = :ENo AND
          `nth` = :nth;
      ");
  
      $statement->bindParam(':ENo', $memberENo,  PDO::PARAM_INT);
      $statement->bindParam(':nth', $target_nth, PDO::PARAM_INT);
  
      $result = $statement->execute();
  
      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
  
      $member['icons'] = $statement->fetchAll();
  
      // メンバーとなるキャラクターのスキル情報を取得
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
          `skills_master_data`.`type`,
          `characters_declarations_skills`.`lines`
        FROM
          `characters_declarations_skills`
        JOIN
          `skills_master_data` ON `skills_master_data`.`skill_id` = `characters_declarations_skills`.`skill`
        WHERE
          `characters_declarations_skills`.`ENo` = :ENo AND
          `characters_declarations_skills`.`nth` = :nth;
      ");
    
      $statement->bindParam(':ENo', $memberENo,  PDO::PARAM_INT);
      $statement->bindParam(':nth', $target_nth, PDO::PARAM_INT);
  
      $result = $statement->execute();
  
      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
  
      $member['skills'] = $statement->fetchAll();
  
      // スキル効果群のJSONをパースして置き換え
      $cnt = count($member['skills']);
      
      for ($i = 0; $i < $cnt; $i++) {
        $member['skills'][$i]['effects'] = json_decode($member['skills'][$i]['effects']);
      }
  
      $members[] = $member;
    }

    // 味方チームの戦闘ユニット群を生成
    $allyUnits = [];
    foreach ($members as $ally) {
      $status = array(
        'ATK' => $ally['ATK'],
        'DEX' => $ally['DEX'],
        'MND' => $ally['MND'],
        'AGI' => $ally['AGI'],
        'DEF' => $ally['DEF']
      );

      $allyUnits[] = new Unit($ally['name'], $status, $ally['skills'], $ally['icons'], $ally['battleLines']);
    }

    // 敵チームの戦闘ユニット群を生成
    $enemyUnits = [];
    foreach ($enemies as $enemy) {
      $status = array(
        'ATK' => $enemy['ATK'],
        'DEX' => $enemy['DEX'],
        'MND' => $enemy['MND'],
        'AGI' => $enemy['AGI'],
        'DEF' => $enemy['DEF']
      );

      $enemyUnits[] = new Unit($enemy['name'], $status, $enemy['skills'], $enemy['icons'], $enemy['battleLines']);
    }

    $battle    = new Battle($allyUnits, $enemyUnits);
    $battleLog = $battle->execute();

    $ENGINES_STORY['stage'] = $stage;
    $ENGINES_STORY['log']   = $battleLog['log'];
    $ENGINES_STORY['diary'] = $declaration['diary'];

    ob_start(); // PHPの実行結果をバッファに出力するように

    require GETENV('GAME_ROOT').'/engines/story.php'; // 探索ログエンジンを呼び出し

    $log = ob_get_contents(); // バッファから実行結果を取得
    ob_end_clean(); // バッファへの出力を終了

    // 結果の保存
    // ディレクトリがなければ作成
    if (!file_exists(GETENV('GAME_ROOT').'/static/results/'.$target_nth.'/')) {
      $result = mkdir(GETENV('GAME_ROOT').'/static/results/'.$target_nth.'/', 0755, true);

      if (!$result) {
        $GAME_PDO->rollBack();
        echo '保存ディレクトリの作成に失敗しました。処理を終了します。';
        exit;
      }
    }

    // 結果を書き出し    
    $result = file_put_contents(GETENV('GAME_ROOT').'/static/results/'.$target_nth.'/'.$declaration['ENo'].'.html', $log, LOCK_SH);

    if (!$result) {
      $GAME_PDO->rollBack();
      echo '結果の書き出しに失敗しました。処理を終了します。';
      exit;
    }
  }

  // 更新確定状態なら更新回数を+1し、更新未確定状態へ変更
  if ($gameStatus['update_status']) {
    $statement = $GAME_PDO->prepare("
      UPDATE
        `game_status`
      SET
        `update_status`   = false,
        `next_update_nth` = `next_update_nth` + 1;
    ");

    $result = $statement->execute();  

    if (!$result) {
      $GAME_PDO->rollBack();
      echo 'ゲームステータスのアップデートに失敗しました。';
      exit;
    }
  }

  // ここまで全て成功した場合はコミット
  $GAME_PDO->commit();
  
?>