<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/battle/battle.php';
  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('stage',   ['non-empty', 'non-negative-integer']) ||
      !validatePOST('members', ['non-empty'])
    ) {
      responseError(400);
    }

    // 探索を行うメンバーのENoを検証して設定
    $memberENos       = [intval($_SESSION['ENo'])]; // 自身は必ずメンバーに含める
    $postedMemberENos = json_decode($_POST['members']);

    // 送信されたメンバー群の型が配列でなければ400(Bad Request)を返して処理を中断
    if (!is_array($postedMemberENos)) {
      responseError(400);
    }

    foreach ($postedMemberENos as $postedMemberENo) {
      if (!is_int($postedMemberENo) || $postedMemberENo <= 0) {
        // 送信されたメンバー群の値に整数でないものがある、あるいは0以下の値があれば400(Bad Request)を返して処理を中断
        responseError(400);
      } else {
        // そうでなければメンバーに追加
        $memberENos[] = $postedMemberENo;
      }
    }

    // パーティの最大人数を超えていれば400(Bad Request)を返して処理を中断
    if ($GAME_CONFIG['PARTY_MEMBERS_MAX'] < count($memberENos)) {
      responseError(400);
    }

    // 連れ出そうとしているキャラクターにお気に入りしていないキャラクターがいないか検証

    // お気に入りしているキャラクターのENo群を取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `characters_favs`.`faved`
      FROM
        `characters_favs`
      JOIN
        `characters` ON `characters`.`ENo` = `characters_favs`.`faved`
      WHERE
        `characters_favs`.`faver` = :ENo AND `characters`.`deleted` = false;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    }

    $favCharacters = $statement->fetchAll();

    foreach ($memberENos as $memberENo) {
      // 自分自身はお気に入り検証をスキップ
      if ($_SESSION['ENo'] == $memberENo) continue;

      $found = false;

      foreach ($favCharacters as $favCharacter) {
        if ($favCharacter['faved'] == $memberENo) {
          $found = true;
          break;
        }
      }

      if (!$found) {
        responseError(403); // 連れ出そうとしているキャラクターにお気に入りしていないキャラクターがいた場合は403(Forbidden)を返して処理を中断
      }
    }

    // メンバーの重複検証
    // 重複を取り除いた結果、メンバーの数が変わっていれば400(Bad Request)を返して処理を中断
    if (count($memberENos) !== count(array_unique($memberENos))) {
      responseError(400);
    }

    // 探索が実行できるかどうかの判定
    $statement = $GAME_PDO->prepare("
      SELECT
        1
      FROM
        `exploration_stages_master_data`
      WHERE
        (
          `stage_id` = :stage AND
          (
            `requirement_stage_id` IS NULL OR
            `requirement_stage_id` IN (
              SELECT
                `completed_stages`.`stage`
              FROM (
                SELECT
                  `exploration_logs`.`stage`,
                  COUNT(`exploration_logs`.`stage`) AS `clear_count`,
                  `m`.`complete_requirement`
                FROM 
                  `exploration_logs`
                JOIN
                  `exploration_stages_master_data` AS `m` ON `m`.`stage_id` = `exploration_logs`.`stage`
                WHERE
                  `leader` = :ENo AND
                  `result` = 'win'
                GROUP BY
                  `exploration_logs`.`stage`
                HAVING
                  `complete_requirement` <= `clear_count`
              ) AS `completed_stages`
            )
          )
        ) AND (
          (SELECT `consumedAP` FROM `characters` WHERE `ENo` = :ENo) < (SELECT `AP` FROM `game_status`)
        );
    ");

    $statement->bindParam(':stage', $_POST['stage']);
    $statement->bindParam(':ENo',   $_SESSION['ENo']);

    $result     = $statement->execute();
    $explorable = $statement->fetch();

    if (!$result) {
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    if (!$explorable) {
      responseError(400); // 探索不可能なステージだった場合は400(Bad Request)を返して処理を中断
    }

    $members = [];
    foreach ($memberENos as $memberENo) {
      // メンバーとなるキャラクターのステータスと戦闘時セリフを取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `characters`.`nickname` AS `name`,
          `characters`.`ATK`,
          `characters`.`DEX`,
          `characters`.`MND`,
          `characters`.`AGI`,
          `characters`.`DEF`,
          IFNULL(`characters_battle_lines`.`start`, '')         AS `lines_start`,
          IFNULL(`characters_battle_lines`.`dodge`, '')         AS `lines_dodge`,
          IFNULL(`characters_battle_lines`.`dodged`, '')        AS `lines_dodged`,
          IFNULL(`characters_battle_lines`.`healed`, '')        AS `lines_healed`,
          IFNULL(`characters_battle_lines`.`healed_own`, '')    AS `lines_healed_own`,
          IFNULL(`characters_battle_lines`.`normal_attack`, '') AS `lines_normal_attack`,
          IFNULL(`characters_battle_lines`.`defeat`, '')        AS `lines_defeat`,
          IFNULL(`characters_battle_lines`.`killed`, '')        AS `lines_killed`,
          IFNULL(`characters_battle_lines`.`killed_ally`, '')   AS `lines_killed_ally`,
          IFNULL(`characters_battle_lines`.`critical`, '')      AS `lines_critical`,
          IFNULL(`characters_battle_lines`.`criticaled`, '')    AS `lines_criticaled`,
          IFNULL(`characters_battle_lines`.`win`, '')           AS `lines_win`,
          IFNULL(`characters_battle_lines`.`even`, '')          AS `lines_even`,
          IFNULL(`characters_battle_lines`.`lose`, '')          AS `lines_lose`
        FROM
          `characters`
        LEFT JOIN
          `characters_battle_lines` ON `characters_battle_lines`.`ENo` = `characters`.`ENo`
        WHERE
          `characters`.`ENo` = :ENo AND `deleted` = false;
      ");
  
      $statement->bindParam(':ENo', $memberENo, PDO::PARAM_INT);
  
      $result = $statement->execute();
  
      if (!$result) {
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
  
      $member = $statement->fetch();
  
      if (!$member) {
        responseError(404); // キャラクターの取得に失敗した場合は404(Not Found)を返して処理を中断
      }

      // 戦闘セリフは1つのキーとしてまとめる
      $member['battleLines'] = array(
        'start'         => $member['lines_start'],
        'dodge'         => $member['lines_dodge'],
        'dodged'        => $member['lines_dodged'],
        'healed'        => $member['lines_healed'],
        'healed_own'    => $member['lines_healed_own'],
        'normal_attack' => $member['lines_normal_attack'],
        'defeat'        => $member['lines_defeat'],
        'killed'        => $member['lines_killed'],
        'killed_ally'   => $member['lines_killed_ally'],
        'critical'      => $member['lines_critical'],
        'criticaled'    => $member['lines_criticaled'],
        'win'           => $member['lines_win'],
        'even'          => $member['lines_even'],
        'lose'          => $member['lines_lose']
      );

      // メンバーとなるキャラクターのアイコン情報を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `name`,
          `url`
        FROM
          `characters_icons`
        WHERE
          `ENo` = :ENo;
      ");
  
      $statement->bindParam(':ENo', $memberENo, PDO::PARAM_INT);
  
      $result = $statement->execute();
  
      if (!$result) {
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
          `characters_skills`.`lines`
        FROM
          `characters_skills`
        JOIN
          `skills_master_data` ON `skills_master_data`.`skill_id` = `characters_skills`.`skill`
        WHERE
          `characters_skills`.`ENo` = :ENo;
      ");
  
      $statement->bindParam(':ENo', $memberENo, PDO::PARAM_INT);
  
      $result = $statement->execute();
  
      if (!$result) {
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

    // ステージ情報の取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `title`,
        `text`
      FROM
        `exploration_stages_master_data`
      WHERE
        `stage_id` = :stage;
    ");

    $statement->bindParam(':stage', $_POST['stage']);

    $result = $statement->execute();
    $stage  = $statement->fetch();

    if (!$result || !$stage) {
      responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    }

    // ステージに出現する敵を取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `enemy`
      FROM
        `exploration_stages_master_data_enemies`
      WHERE
        `stage` = :stage;
    ");

    $statement->bindParam(':stage', $_POST['stage'], PDO::PARAM_INT);

    $result      = $statement->execute();
    $enemieDatas = $statement->fetchAll();

    if (!$result || !$enemieDatas) {
      responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返して処理を中断
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
        responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返して処理を中断
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
        responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
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

    // ステージのドロップアイテム情報の取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `exploration_stages_master_data_drop_items`.`item`,
        `items_master_data`.`name`,
        `exploration_stages_master_data_drop_items`.`rate_numerator`,
        `exploration_stages_master_data_drop_items`.`rate_denominator`
      FROM
        `exploration_stages_master_data_drop_items`
      JOIN
        `items_master_data` ON `items_master_data`.`item_id` = `exploration_stages_master_data_drop_items`.`item`
      WHERE
        `exploration_stages_master_data_drop_items`.`stage` = :stage AND
        `items_master_data`.`creatable` = true;
    ");

    $statement->bindParam(':stage', $_POST['stage']);

    $result         = $statement->execute();
    $stageDropItems = $statement->fetchAll();

    if (!$result) {
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    }

    // クリア時にドロップするアイテムを計算
    $dropItemsWhenCleared = [];
    foreach ($stageDropItems as $stageDropItem) {
      if (mt_rand(1, $stageDropItem['rate_denominator']) <= $stageDropItem['rate_numerator']) {
        $dropItemsWhenCleared[] = array(
          'item' => $stageDropItem['item'],
          'name' => $stageDropItem['name']
        );
      }
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

    // 探索ログを出力
    $ENGINES_EXPLORATION['stage']      = $stage;
    $ENGINES_EXPLORATION['drop_items'] = $dropItemsWhenCleared;
    $ENGINES_EXPLORATION['log']        = $battleLog['log'];
    $ENGINES_EXPLORATION['result']     = $battleLog['result'];

    ob_start(); // PHPの実行結果をバッファに出力するように

    require GETENV('GAME_ROOT').'/engines/exploration.php'; // 探索ログエンジンを呼び出し

    $log = ob_get_contents(); // バッファから実行結果を取得
    ob_end_clean(); // バッファへの出力を終了

    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // 消費したAP量の加算
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `consumedAP` = `consumedAP` + 1
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // 探索ログデータの作成
    $statement = $GAME_PDO->prepare("
      INSERT INTO `exploration_logs` (
        `leader`,
        `stage`,
        `result`
      ) VALUES (
        :leader,
        :stage,
        :result
      );
    ");

    $statement->bindParam(':leader', $_SESSION['ENo']);
    $statement->bindParam(':stage',  $_POST['stage']);
    $statement->bindParam(':result', $battleLog['result']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    $lastInsertId = intval($GAME_PDO->lastInsertId()); // 登録されたidを取得

    // 探索ログのメンバーを登録
    foreach ($memberENos as $memberENo) {
      $statement = $GAME_PDO->prepare("
        INSERT INTO `exploration_logs_members` (
          `log`,
          `member`
        ) VALUES (
          :log,
          :member
        );
      ");

      $statement->bindParam(':log',    $lastInsertId);
      $statement->bindParam(':member', $memberENo);

      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
    }

    // idをファイル名としてログファイルを保存
    // 1ディレクトリに存在するファイル数が多すぎると速度低下の原因となるため
    // 5桁以上の部分ごとにディレクトリを変更
    // 例：
    //     id=1 : /static/logs/1/1.html
    //     id=2 : /static/logs/1/2.html
    //
    //              …
    //
    //  id=9999 : /static/logs/1/9999.html
    // id=10000 : /static/logs/2/10000.html
    // id=10001 : /static/logs/2/10001.html …

    $directory = strval(floor($lastInsertId/10000) + 1); // ディレクトリ名を計算

    // ディレクトリがなければ作成
    if (!file_exists(GETENV('GAME_ROOT').'/static/logs/'.$directory.'/')) {
      $result = mkdir(GETENV('GAME_ROOT').'/static/logs/'.$directory.'/', 0644, true);

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // ディレクトリの作成に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
    }

    // 勝利していた場合
    if ($battleLog['result'] === 'win') {
      // アイテムの取得処理
      foreach ($dropItemsWhenCleared as $item) {
        // キャラクターの所持アイテム数をアップデート
        $statement = $GAME_PDO->prepare("
          INSERT INTO `characters_items` (
            `ENo`,
            `item`,
            `number`
          ) VALUES (
            :ENo,
            :item,
            1
          )

          ON DUPLICATE KEY UPDATE
            `number` = `number` + 1;
        ");
    
        $statement->bindParam(':ENo',  $_SESSION['ENo']);
        $statement->bindParam(':item', $item['item']);
    
        $result = $statement->execute();
    
        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
        }

        // アイテムの排出量をアップデート
        $statement = $GAME_PDO->prepare("
          INSERT INTO `items_yield` (
            `item`,
            `yield`
          ) VALUES (
            :item,
            1
          )

          ON DUPLICATE KEY UPDATE
            `yield` = `yield` + 1
        ");
    
        $statement->bindParam(':item', $item['item']);
    
        $result = $statement->execute();
    
        if (!$result) {
          $GAME_PDO->rollBack();
          responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
        }
      }
    }

    // ログの結果を書き出し    
    $result = file_put_contents(GETENV('GAME_ROOT').'/static/logs/'.$directory.'/'.$lastInsertId.'.html', $log, LOCK_SH);

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // ファイルの書き込みに失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // ここまで全て成功した場合はコミット
    $GAME_PDO->commit();
  }

  // 現在のAP量を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `consumedAP`,
      (SELECT `AP` FROM `game_status`) AS `distributedAP`
    FROM
      `characters`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();
  $data   = $statement->fetch();

  if (!$result || !$data) {
    // 結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  $AP = $data['distributedAP'] - $data['consumedAP'];

  // 選択可能なステージとその情報を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `stage_id`,
      `title`,
      `complete_requirement`,
      (SELECT COUNT(*) FROM `exploration_logs` WHERE `stage` = `exploration_stages_master_data`.`stage_id` AND `leader` = :ENo AND `result` = 'win') AS `clear_count`
    FROM
      `exploration_stages_master_data`
    WHERE
      `requirement_stage_id` IS NULL OR
      `requirement_stage_id` IN (
        SELECT
          `completed_stages`.`stage`
        FROM (
          SELECT
            `exploration_logs`.`stage`,
            COUNT(`exploration_logs`.`stage`) AS `clear_count`,
            `m`.`complete_requirement`
          FROM 
            `exploration_logs`
          JOIN
            `exploration_stages_master_data` AS `m` ON `m`.`stage_id` = `exploration_logs`.`stage`
          WHERE
            `leader` = :ENo AND
            `result` = 'win'
          GROUP BY
            `exploration_logs`.`stage`
          HAVING
            `complete_requirement` <= `clear_count`
        ) AS `completed_stages`
      );
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    // 結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  $stages = $statement->fetchAll();

  // 連れ出し可能なキャラクター（＝お気に入りしているキャラクター）を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `characters`.`ENo`,
      `characters`.`nickname`,
      `characters`.`ATK`,
      `characters`.`DEX`,
      `characters`.`MND`,
      `characters`.`AGI`,
      `characters`.`DEF`,
      `characters`.`summary`,
      (SELECT `url` FROM `characters_icons` WHERE `characters_icons`.`ENo` = `characters`.`ENo` LIMIT 1) AS `icon`
    FROM
      `characters_favs`
    JOIN
      `characters` ON `characters`.`ENo` = `characters_favs`.`faved`
    WHERE
      `characters_favs`.`faver` = :ENo AND `characters`.`deleted` = false;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  $selectableCharacters = $statement->fetchAll();

  $PAGE_SETTING['TITLE'] = '探索';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.remaining-ap {
  padding: 20px 20px 0 20px;
  font-weight: bold;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  font-size: 24px;
  color: #666;
}

.selectable-character {
  display: flex;
  padding: 10px;
  border: 1px solid lightgray;
  margin: 10px;
  border-radius: 4px;
  cursor: pointer;
  user-select: none;
}

.icon {
  width: 60px;
}

.description {
  flex-grow: 1;
  display: block;
  margin-left: 10px;
}

.name {
  font-size: 16px;
  font-weight: bold;
  color: #222222;
}

.eno {
  font-size: 13px;
  margin-left: 3px;
  color: gray;
}

.statuses {
  margin: 0;
}

.statuses th, .statuses td {
  padding: 0 8px 0 0;
  border: none;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
}

.status-name {
  font-weight: bold;
  color: #555;
  margin-right: 20px;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>探索</h1>

<section class="remaining-ap">所持AP: <?=$AP?></section>

<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
<section>
  <h2>探索結果</h2>

  <p>
    <?=$stage['title']?>を探索しました。ログは<a href="<?=$GAME_CONFIG['URI']?>logs/<?=$directory.'/'.$lastInsertId.'.html'?>" target="_blank">こちら</a>よりアクセスできます。
  </p>
</section>
<?php } ?>

<form id="form" method="post">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
  <input type="hidden" id="members" name="members">

  <section>
    <h2>ステージ選択</h2>

    <section class="form">
      <div class="form-title">探索先</div>
      <select id="input-stage" class="form-input" name="stage">
        <option value="null">▼探索先を選択</option>
<?php foreach ($stages as $stage) { ?>
        <option value="<?=$stage['stage_id']?>"><?=$stage['stage_id']?>. <?=$stage['title']?> (<?=$stage['clear_count']?>/<?=$stage['complete_requirement']?>)</option> 
<?php } ?>
      </select>
    </section>
  </section>

<section>
  <h2>パーティメンバー選択</h2>
  <div class="form-description">
    連れ出すパーティメンバーを選択します。<br>
    お気に入りしているキャラクターの中から選択することができます。<br>
    パーティーメンバーはあなたを含めて最大<?=$GAME_CONFIG['PARTY_MEMBERS_MAX']?>人までです。
  </div>
  <div class="form-title">
    パーティメンバー（クリックで選択キャンセル）
  </div>

  <div id="selected-characters-area"></div>
  
  <br>

  <div class="form-title">
    連れ出せるキャラクター（クリックで選択）
  </div>

<?php if (!$selectableCharacters) { ?>
  <div class="form-description">
    お気に入りしているキャラクターがいません。<br>
    お気に入りはキャラクター一覧からキャラクターページにアクセスし<br>
    「お気に入りする」ボタンをクリックすることで行なえます。
  </div>
<?php } else { ?>
  <section id="selectable-characters-area">
<?php foreach ($selectableCharacters as $selectableCharacter) { ?>
    <section class="selectable-character" data-eno="<?=$selectableCharacter['ENo']?>">
      <div class="icon">
        <?php
          $COMPONENT_ICON['src'] = $selectableCharacter['icon'];
          include GETENV('GAME_ROOT').'/components/icon.php';
        ?>
      </div>
      <div class="description">
        <div>
          <span class="name"><?=htmlspecialchars($selectableCharacter['nickname'])?></span>
          <span class="eno">&lt; ENo.<?=$selectableCharacter['ENo']?> &gt;</span>
        </div>
        <table class="statuses">
          <tbody>
            <tr>
              <td class="status-name">ATK</td>
              <td class="status-value"><?=$selectableCharacter['ATK']?></td>
              <td class="status-name">DEX</td>
              <td class="status-value"><?=$selectableCharacter['DEX']?></td>
              <td class="status-name">MND</td>
              <td class="status-value"><?=$selectableCharacter['MND']?></td>
              <td class="status-name">AGI</td>
              <td class="status-value"><?=$selectableCharacter['AGI']?></td>
              <td class="status-name">DEF</td>
              <td class="status-value"><?=$selectableCharacter['DEF']?></td>
            </tr>
          </tbody>
        </table>
        <div class="summary">
          <?=htmlspecialchars($selectableCharacter['summary'])?>
        </div>
      </div>
    </section>
<?php } ?>
  </section>
<?php } ?>
</section>

<br>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button class="button" type="submit">探索</button>
  </div>
</form>


<script>
<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
  // ログファイルを別タブで開く
  window.open('<?=$GAME_CONFIG['URI']?>logs/<?=$directory.'/'.$lastInsertId.'.html'?>');

<?php } ?>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）

  // 選択されているキャラクターの取得
  function getSelectedCharacterENos() {
    var selectedENos = [];

    $('#selected-characters-area > .selectable-character').each(function(i, element){
      selectedENos.push($(element).data().eno);
    });

    return selectedENos;
  }

  // キャラクターの選択
  $('#selectable-characters-area').on('click', '.selectable-character', function() {
    if (<?=$GAME_CONFIG['PARTY_MEMBERS_MAX']?> - 1 <= getSelectedCharacterENos().length) {
      alert("すでにパーティメンバーは埋まっています");
    } else {
      $('#selected-characters-area').append(this);
    }
  });

  // キャラクターの選択解除
  $('#selected-characters-area').on('click', '.selectable-character', function() {
    $('#selectable-characters-area').append(this);

    // ENo順に並び替え
    $('#selectable-characters-area').html(
      $('#selectable-characters-area > .selectable-character').sort(function(a, b) {
        return $(a).data().eno - $(b).data().eno;
      })
    );
  });

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

  $('#form').submit(function(){
    // 値を取得
    var inputStage = $('#input-stage').val();

    // 入力値検証
    // 行き先が選択されていない場合エラーメッセージを表示して送信を中断
    if (inputStage == 'null') {
      showErrorMessage('行き先が選択されていません');
      return false;
    }

    // AP量が0ならエラーメッセージを表示して送信を中断
    if (<?=$AP?> == 0) {
      showErrorMessage('APが残っていません');
      return false;
    }

    // レスポンス待ちの場合アラートを表示して送信を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 送信
    $('#members').val(JSON.stringify(getSelectedCharacterENos())); // 連れ出しキャラクターをJSON化されたENoの配列として#membersに設定

    // レスポンス待ちをONに
    waitingResponse = true;
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>