<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/notification.php';
  require_once GETENV('GAME_ROOT').'/utils/validation.php';
  require_once GETENV('GAME_ROOT').'/utils/parser.php';
  require_once GETENV('GAME_ROOT').'/utils/decoration.php';

  // POST/GETで$roomIdと$modeと$rootを取得する
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // POSTの場合
    // 入力値検証
    if (
      !validatePOST('action', ['non-empty']) ||
      !validatePOST('mode',   []) ||
      !validatePOST('room',   ['non-empty'])
    ) {
      http_response_code(400); 
      exit;
    }

    $roomId = $_POST['room'];                                 // POSTのroomの値を取得
    $mode   = isset($_POST['mode']) ? $_POST['mode'] : 'all'; // POSTのmodeの値を取得、なければall
    $root   = isset($_POST['root']) ? $_POST['root'] : null;  // POSTのmodeの値を取得、なければnull
  } else {
    // GETの場合
    $roomId = isset($_GET['room']) ? $_GET['room'] : $GAME_CONFIG['PUBLIC_ROOMS'][0]['alias']; // URLパラメータのroomの値を取得、なければ公式トークルームの最初の指定
    $mode   = isset($_GET['mode']) ? $_GET['mode'] : 'all'; // URLパラメータのmodeの値を取得、なければall
    $root   = isset($_GET['root']) ? $_GET['root'] : null;  // URLパラメータのrootの値を取得、なければnull
  }

  // $roomIdを検証して$RNoを設定
  if (preg_match('/^([1-9][0-9]*)$/', $roomId)) {
    // 正の整数の場合、そのまま$RNoとして設定。ただしその値が$GAME_CONFIG['PUBLIC_ROOMS']に設定されているものだった場合は404を返して処理を中断
    $RNo = intval($roomId);
    foreach ($GAME_CONFIG['PUBLIC_ROOMS'] as $publicRoom) {
      if ($RNo == $publicRoom['RNo']) {
        http_response_code(404); 
        exit;
      }
    }
  } else {
    // 正の整数でない場合、$GAME_CONFIG['PUBLIC_ROOMS']にaliasが指定されているものか検証。指定されているものであればその該当のRNoをセット、指定がなければ404を返して処理を中断
    $publicRoomAliasMatched = false;
    foreach ($GAME_CONFIG['PUBLIC_ROOMS'] as $publicRoom) {
      if ($roomId == $publicRoom['alias']) {
        $publicRoomAliasMatched = true;
        $RNo = $publicRoom['RNo'];
        break;
      }
    }
    
    if (!$publicRoomAliasMatched) {
      http_response_code(404); 
      exit;
    }
  }

  // $modeがall, rel, own, fav, resのいずれでもなければallに
  if ($mode != 'all' && $mode != 'rel' && $mode != 'own' && $mode != 'fav' && $mode != 'res') {
    $mode = 'all';
  }

  // $modeがresで$rootの設定がなければ400を返して処理を中断
  if ($mode == 'res' && $root == null) {
    http_response_code(400); 
    exit;
  }

  // 現在のページ
  // pageの指定があればその値-1、指定がなければ0
  // インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $page = isset($_GET['page']) ? intval($_GET['page']) -1 : 0;

  // ページが負なら400(Bad Request)を返して処理を中断
  if ($page < 0) {
    http_response_code(400); 
    exit;
  }

  // DBからキャラクターを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `ENo`,
      `nickname`,
      (SELECT GROUP_CONCAT(`name`, '\n', `url` SEPARATOR '\n') FROM `characters_icons` WHERE `ENo` = :ENo GROUP BY `ENo`) AS `icons`
    FROM
      `characters`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();
  $user   = $statement->fetch();

  if (!$result || !$user) {
    // SQLの実行または結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  // DBからトークルームのデータを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `RNo`,
      `administrator`,
      `title`,
      `description`,
      `last_posted_at`,
      `official`,
      (SELECT GROUP_CONCAT(`tag` SEPARATOR ' ') FROM `rooms_tags` WHERE `RNo` = :RNo GROUP BY `RNo`) AS `tags`,
      IFNULL((SELECT true FROM `rooms_subscribers` WHERE `RNo` = :RNo AND `subscriber` = :ENo), false) AS `subscribing`
    FROM
      `rooms`
    WHERE
      `RNo`     = :RNo AND
      `deleted` = false;
  ");

  $statement->bindParam(':RNo', $RNo);
  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();
  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  $room = $statement->fetch();

  if (!$room) {
    // 実行結果の取得に失敗した場合は404(Not Found)を返し処理を中断
    http_response_code(404); 
    exit;
  }

  // POSTの処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 投稿処理
    if ($_POST['action'] == 'post') {
      // 入力値検証
      if (
        !validatePOST('refer',   ['natural-number'])                        ||
        !validatePOST('icon',    ['single-line', 'disallow-special-chars']) ||
        !validatePOST('name',    ['single-line', 'disallow-special-chars'], $GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH']) ||
        !validatePOST('message', ['non-empty',   'disallow-special-chars'], $GAME_CONFIG['ROOM_MESSAGE_MAX_LENGTH'])
      ) {
        http_response_code(400); 
        exit;
      }

      // 返信を行う場合
      if ($_POST['refer']) {
        $refer = $_POST['refer']; // referにPOSTの値を設定

        // 返信先のルート発言IDと返信先群の取得
        $statement = $GAME_PDO->prepare("
          SELECT
            `messages`.`ENo`,
            `messages`.`id`,
            `messages`.`refer_root`,
            IFNULL(GROUP_CONCAT(DISTINCT `messages_recipients`.`ENo` ORDER BY `messages_recipients`.`id` SEPARATOR ','), '') AS `recipients`
          FROM
            `messages`
          LEFT JOIN
            `messages_recipients` ON `messages`.`id` = `messages_recipients`.`message`
          WHERE
            `messages`.`id` = :refer AND
            `messages`.`deleted` = false;
        ");

        $statement->bindParam(':refer', $refer);

        $result = $statement->execute();

        if (!$result) {
          // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
          http_response_code(500); 
          exit;
        }

        $data = $statement->fetch();
      
        if (!$data) {
          // 実行結果の取得に失敗した場合は404(Not Found)を返し処理を中断
          http_response_code(404); 
          exit;
        }

        $referRoot  = $data['refer_root'] ? $data['refer_root'] : $data['id'];   // 返信先にrefer_rootが存在する場合はそれを、ない場合は返信先のidをreferRootに
        $recipients = array_filter(explode(',', $data['recipients']), "strlen"); // GROUP_CONCATされたrecipientsを,で分割し、空要素を削除

        // recipientsに対象が含まれていなければ対象を追加
        if (!in_array($data['ENo'], $recipients)) {
          $recipients[] = $data['ENo'];
        }

        // recipientsに自身が含まれていなければ自身を追加
        if (!in_array($_SESSION['ENo'], $recipients)) {
          $recipients[] = $_SESSION['ENo'];
        }

      } else {
        // 返信を行わない場合referとrefer_rootをnullに、recipicientsは空の配列に
        $refer      = null;
        $referRoot  = null;
        $recipients = array();
      }

      // トランザクション開始
      $GAME_PDO->beginTransaction();
 
      // メッセージの登録
      $statement = $GAME_PDO->prepare("
        INSERT INTO `messages` (
          `RNo`,
          `ENo`,
          `refer`,
          `refer_root`,
          `name`,
          `icon`,
          `message`
        ) VALUES (
          :RNo,
          :ENo,
          :refer,
          :referRoot,
          :name,
          :icon,
          :message
        );
      ");

      $statement->bindParam(':RNo',       $RNo);
      $statement->bindParam(':ENo',       $_SESSION['ENo']);
      $statement->bindParam(':refer',     $refer);
      $statement->bindParam(':referRoot', $referRoot);
      $statement->bindParam(':name',      $_POST['name']);
      $statement->bindParam(':icon',      $_POST['icon']);
      $statement->bindParam(':message',   $_POST['message']);

      $result = $statement->execute();

      if (!$result) {
        // 失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
        http_response_code(500); 
        $GAME_PDO->rollBack();
        exit;
      }

      $lastInsertId = intval($GAME_PDO->lastInsertId()); // idを取得

      // 返信先の登録
      foreach ($recipients as $recipient) {
        $statement = $GAME_PDO->prepare("
          INSERT INTO `messages_recipients` (
            `message`,
            `ENo`
          ) VALUES (
            :message,
            :recipient
          );
        ");

        $statement->bindParam(':message',   $lastInsertId);
        $statement->bindParam(':recipient', $recipient);

        $result = $statement->execute();
        
        if (!$result) {
          // 失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
          http_response_code(500);
          $GAME_PDO->rollBack();
          exit;
        }
      }

      // ここまで全て成功した場合はコミット
      $GAME_PDO->commit();

      // 返信の場合
      if ($_POST['refer']) {
        // 返信通知が有効な返信先へ通知を作成（自身を除く）
        $statement = $GAME_PDO->prepare("
          INSERT INTO `notifications` (
            `ENo`,
            `type`,
            `target`,
            `message`
          )

          SELECT
            `messages`.`ENo`,
            'replied',
            :id,
            ''
          FROM
            `messages`
          JOIN
            `messages_recipients` ON `messages_recipients`.`message` = `messages`.`id`
          JOIN
            `characters` ON `characters`.`ENo` = `messages_recipients`.`ENo`
          WHERE
            `messages_recipients`.`ENo`     != :ENo    AND
            `messages_recipients`.`message` =  :id     AND
            `characters`.`notification_replied` = true AND
            `characters`.`deleted` = false;
        ");

        $statement->bindParam(':ENo', $_SESSION['ENo']);
        $statement->bindParam(':id',  $lastInsertId);

        $statement->execute();

        // Webhookが入力され、Discord返信通知が有効な返信先へ通知を送信（自身を除く）
        $statement = $GAME_PDO->prepare("
          SELECT
            `characters`.`webhook`
          FROM
            `messages_recipients`
          JOIN
            `characters` ON `characters`.`ENo` = `messages_recipients`.`ENo`
          WHERE
            `messages_recipients`.`ENo`     != :ENo  AND
            `messages_recipients`.`message` =  :id   AND
            `characters`.`deleted`          =  false AND
            `characters`.`webhook`          != ''    AND
            `characters`.`notification_webhook_direct_message` = true;
        ");

        $statement->bindParam(':ENo', $_SESSION['ENo']);
        $statement->bindParam(':id',  $lastInsertId);

        $result   = $statement->execute();
        $webhooks = $statement->fetchAll();

        if ($result && $webhooks) {
          foreach ($webhooks as $webhook) {
            if ($room['official']) {
              notifyDiscord($webhook['webhook'], $room['title'].'にてENo.'.$user['ENo'].' '.$user['nickname'].'からの返信があります。');
            } else {
              notifyDiscord($webhook['webhook'], 'RNo.'.$RNo.' '.$room['title'].'にてENo.'.$user['ENo'].' '.$user['nickname'].'からの返信があります。');
            }
          }
        }
      }

      // トークルーム購読者へ通知を送信（自身を除く）
      // 通知を見ていない間にすでに対象のトークルームの通知が行われている場合、その通知にカウントを足す
      $statement = $GAME_PDO->prepare("
        UPDATE
          `notifications`
        SET
          `count` = `count` + 1
        WHERE
          `notifications`.`ENo`     != :ENo          AND
          `notifications`.`type`    =  'new_arrival' AND
          `notifications`.`target`  =  :RNo          AND
          `notifications`.`ENo` IN (
            SELECT
              `rooms_subscribers`.`subscriber`
            FROM
              `rooms_subscribers`
            JOIN
              `characters` ON `characters`.`ENo` = `rooms_subscribers`.`subscriber`
            WHERE
              `rooms_subscribers`.`RNo` = :RNo               AND
              `characters`.`notification_new_arrival` = true AND
              `characters`.`deleted` = false                 AND
              `characters`.`notifications_last_checked_at` < `notifications`.`notificated_at`
          )
      ");

      $statement->bindParam(':ENo', $_SESSION['ENo']);
      $statement->bindParam(':RNo', $RNo);

      $statement->execute();

      // 通知を見ていない期間に対象のトークルームの通知がない場合、新規に通知を追加する
      $statement = $GAME_PDO->prepare("
        INSERT INTO `notifications` (
          `ENo`,
          `type`,
          `target`,
          `count`,
          `message`
        )

        SELECT
          `rooms_subscribers`.`subscriber`,
          'new_arrival',
          :RNo,
          1,
          ''
        FROM
          `rooms_subscribers`
        JOIN
          `characters` ON `characters`.`ENo` = `rooms_subscribers`.`subscriber`
        WHERE
          `rooms_subscribers`.`RNo`        =  :RNo       AND
          `rooms_subscribers`.`subscriber` != :ENo       AND
          `characters`.`notification_new_arrival` = true AND
          `characters`.`deleted`= false                  AND
          `rooms_subscribers`.`subscriber` NOT IN (
            SELECT
              `notifications`.`ENo`
            FROM
              `notifications`
            JOIN
              `characters` AS `C` ON `C`.`ENo` = `notifications`.`ENo`
            WHERE
              `notifications`.`type`   =  'new_arrival' AND
              `notifications`.`target` =  :RNo          AND
              `C`.`notifications_last_checked_at` < `notifications`.`notificated_at`
          );
      ");

      $statement->bindParam(':ENo', $_SESSION['ENo']);
      $statement->bindParam(':RNo', $RNo);

      $statement->execute();

      // トークルーム購読者にDiscord通知を送信
      $statement = $GAME_PDO->prepare("
        SELECT
          `characters`.`webhook`
        FROM
          `rooms_subscribers`
        JOIN
          `characters` ON `characters`.`ENo` = `rooms_subscribers`.`subscriber`
        WHERE
          `rooms_subscribers`.`RNo`        =  :RNo AND
          `rooms_subscribers`.`subscriber` != :ENo AND
          `characters`.`webhook`           != ''   AND
          `characters`.`notification_webhook_new_arrival` = true
      ");

      $statement->bindParam(':ENo', $_SESSION['ENo']);
      $statement->bindParam(':RNo', $RNo);

      $result      = $statement->execute();
      $subscribers = $statement->fetchAll();

      if ($result && $subscribers) {
        foreach ($subscribers as $subscriber) {
          notifyDiscord($subscriber['webhook'], 'RNo.'.$RNo.' '.$room['title'].'に新規メッセージがあります。');
        }
      }

      // 発言を行った部屋の最終投稿時刻を更新
      $statement = $GAME_PDO->prepare("
        UPDATE
          `rooms`
        SET
          `last_posted_at` = current_timestamp
        WHERE
          `RNo` = :RNo;
      ");

      $statement->bindParam(':RNo', $RNo);

      $statement->execute();
    } else if ($_POST['action'] == 'delete') {
      // 削除処理の場合
      // 入力値検証
      // 受け取ったデータにidがなければ400(Bad Request)を返して処理を中断
      if (!validatePOST('id', ['non-empty', 'natural-number'])) {
        http_response_code(400); 
        exit;
      }

      // DBから削除対象のメッセージを取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `ENo`
        FROM
          `messages`
        WHERE
          `id` = :id AND `deleted` = false;
      ");

      $statement->bindValue(':id', $_POST['id'], PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        http_response_code(500); 
        exit;
      }

      $data = $statement->fetch();
    
      if (!$data) {
        // 実行結果の取得に失敗した場合は404(Not Found)を返し処理を中断
        http_response_code(404); 
        exit;
      }

      if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR && $data['ENo'] != $_SESSION['ENo']) {
        // 削除しようとしているのがゲーム管理者ではなく、削除対象の投稿者が削除者と異なる場合403(Forbidden)を返し処理を中断
        http_response_code(403);
        exit;
      }
      
      // 対象の投稿を削除状態に
      $statement = $GAME_PDO->prepare("
        UPDATE
          `messages`
        SET
          `deleted` = true
        WHERE
          `id` = :id;
      ");

      $statement->bindValue(':id', $_POST['id'], PDO::PARAM_INT);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        http_response_code(500); 
        exit;
      }
    } else if ($_POST['action'] == 'subscribe') {
      // 購読処理の場合
      if ($room['official'] || $room['subscribing']) {
        // 対象が公式トークルーム、あるいはすでに購読している場合は400(Bad Request)を返し処理を中断
        http_response_code(500); 
        exit;
      }
      
      // 購読処理を行う
      $statement = $GAME_PDO->prepare("
        INSERT INTO `rooms_subscribers` (
          `RNo`,
          `subscriber`
        ) VALUES (
          :RNo,
          :subscriber
        );
      ");

      $statement->bindParam(':RNo',        $room['RNo']);
      $statement->bindParam(':subscriber', $_SESSION['ENo']);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        http_response_code(500); 
        exit;
      }

      $room['subscribing'] = true;
    } else if ($_POST['action'] == 'unsubscribe') {    
      // 購読解除処理の場合
      if (!$room['subscribing']) {
        // 未購読の場合は400(Bad Request)を返し処理を中断
        http_response_code(500); 
        exit;
      }
      
      // 購読解除処理を行う
      $statement = $GAME_PDO->prepare("
        DELETE FROM
          `rooms_subscribers`
        WHERE
          `RNo` = :RNo AND `subscriber` = :subscriber;
      ");

      $statement->bindParam(':RNo',        $room['RNo']);
      $statement->bindParam(':subscriber', $_SESSION['ENo']);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        http_response_code(500); 
        exit;
      }

      $room['subscribing'] = false;
    } else {
      // 以上のアクションのどれでもない場合400(Bad Request)を返し処理を中断
      http_response_code(400); 
      exit;
    }
  }

  // DBからメッセージを取得
  // 削除フラグが立っておらずページに応じた範囲のメッセージを検索
  // デフォルトの設定ではページ0であれば0件飛ばして20件、ページ1であれば20件飛ばして20件、ページ2であれば40件飛ばして20件、ページnであればn×20件飛ばして20件を表示します。
  // ただし、次のページがあるかどうか検出するために1件余分に取得します。
  // 21件取得してみて21件取得できれば次のページあり、取得結果の数がそれ未満であれば次のページなし（最終ページ）として扱います。

  // モードごとに条件を指定
  switch ($mode) {
    case 'all':
      // all:指定のトークルームの、削除されておらず、発言者がミュート・ブロック・被ブロックの関係でない発言を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `messages`.`id`,
          `messages`.`ENo`,
          `messages`.`refer`,
          `messages`.`refer_root`,
          `messages`.`icon`,
          `messages`.`name`,
          `messages`.`message`,
          `messages`.`posted_at`,
          IFNULL(GROUP_CONCAT(DISTINCT `characters`.`nickname`, '\n', `messages_recipients`.`ENo` ORDER BY `messages_recipients`.`id` SEPARATOR '\n'), '') AS `recipients`
        FROM
          `messages`
        LEFT JOIN
          `messages_recipients` ON `messages`.`id` = `messages_recipients`.`message`
        LEFT JOIN
          `characters` ON `messages_recipients`.`ENo` = `characters`.`ENo`
        WHERE
          `messages`.`RNo`     = :RNo  AND
          `messages`.`deleted` = false AND
          `messages`.`ENo` NOT IN (
            SELECT `muted`   FROM `characters_mutes`  WHERE `muter`   = :ENo UNION ALL
            SELECT `blocked` FROM `characters_blocks` WHERE `blocker` = :ENo UNION ALL
            SELECT `blocker` FROM `characters_blocks` WHERE `blocked` = :ENo
          )
        GROUP BY
          `messages`.`id`
        ORDER BY
          `messages`.`posted_at` DESC
        LIMIT
          :offset, :number;
      ");
      break;

    case 'rel':
      // rel:指定のトークルームの、削除されておらず、発言者がブロック・被ブロックの関係でない自身の発言あるいは自信を返信先に含む発言を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `messages`.`id`,
          `messages`.`ENo`,
          `messages`.`refer`,
          `messages`.`refer_root`,
          `messages`.`icon`,
          `messages`.`name`,
          `messages`.`message`,
          `messages`.`posted_at`,
          IFNULL(GROUP_CONCAT(DISTINCT `characters`.`nickname`, '\n', `messages_recipients`.`ENo` ORDER BY `messages_recipients`.`id` SEPARATOR '\n'), '') AS `recipients`
        FROM
          `messages`
        LEFT JOIN
          `messages_recipients` ON `messages`.`id` = `messages_recipients`.`message`
        LEFT JOIN
          `characters` ON `messages_recipients`.`ENo` = `characters`.`ENo`
        WHERE
          `messages`.`RNo`     = :RNo  AND
          `messages`.`deleted` = false AND
          (
            `messages`.`ENo` = :ENo OR
            EXISTS (SELECT * FROM `messages_recipients` AS `mr` WHERE `messages_recipients`.`message` = `mr`.`message` && `mr`.`ENo` = :ENo)
          ) AND
          `messages`.`ENo` NOT IN (
            SELECT `blocked` FROM `characters_blocks` WHERE `blocker` = :ENo UNION ALL
            SELECT `blocker` FROM `characters_blocks` WHERE `blocked` = :ENo
          )
        GROUP BY
          `messages`.`id`
        ORDER BY
          `messages`.`posted_at` DESC
        LIMIT
          :offset, :number;
      ");
      break;

    case 'own':
      // own:指定のトークルームの削除されていない自身の発言を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `messages`.`id`,
          `messages`.`ENo`,
          `messages`.`refer`,
          `messages`.`refer_root`,
          `messages`.`icon`,
          `messages`.`name`,
          `messages`.`message`,
          `messages`.`posted_at`,
          IFNULL(GROUP_CONCAT(DISTINCT `characters`.`nickname`, '\n', `messages_recipients`.`ENo` ORDER BY `messages_recipients`.`id` SEPARATOR '\n'), '') AS `recipients`
        FROM
          `messages`
        LEFT JOIN
          `messages_recipients` ON `messages`.`id` = `messages_recipients`.`message`
        LEFT JOIN
          `characters` ON `messages_recipients`.`ENo` = `characters`.`ENo`
        WHERE
          `messages`.`RNo`     = :RNo  AND
          `messages`.`deleted` = false AND
          `messages`.`ENo`     = :ENo
        GROUP BY
          `messages`.`id`
        ORDER BY
          `messages`.`posted_at` DESC
        LIMIT
          :offset, :number;
      ");

      break;

    case 'fav':
      // fav:指定のトークルームの削除されておらず、ミュートしていないお気に入りキャラクターの発言あるいは自分の発言を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `messages`.`id`,
          `messages`.`ENo`,
          `messages`.`refer`,
          `messages`.`refer_root`,
          `messages`.`icon`,
          `messages`.`name`,
          `messages`.`message`,
          `messages`.`posted_at`,
          IFNULL(GROUP_CONCAT(DISTINCT `characters`.`nickname`, '\n', `messages_recipients`.`ENo` ORDER BY `messages_recipients`.`id` SEPARATOR '\n'), '') AS `recipients`
        FROM
          `messages`
        LEFT JOIN
          `messages_recipients` ON `messages`.`id` = `messages_recipients`.`message`
        LEFT JOIN
          `characters` ON `messages_recipients`.`ENo` = `characters`.`ENo`
        WHERE
          `messages`.`RNo`     = :RNo  AND
          `messages`.`deleted` = false AND
          (
            `messages`.`ENo` = :ENo OR
            EXISTS (SELECT * FROM `characters_favs` WHERE `faver` = :ENo AND `faved` = `messages`.`ENo`)
          ) AND
          `messages`.`ENo` NOT IN (
            SELECT `muted` FROM `characters_mutes` WHERE `muter` = :ENo
          )
        GROUP BY
          `messages`.`id`
        ORDER BY
          `messages`.`posted_at` DESC
        LIMIT
          :offset, :number;
      ");

      break;

    case 'res':
      // res:指定のトークルームの削除されておらず、、発言者がミュート・ブロック・被ブロックの関係でない、指定のrootを持つ発言あるいはrootの発言を取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `messages`.`id`,
          `messages`.`ENo`,
          `messages`.`refer`,
          `messages`.`refer_root`,
          `messages`.`icon`,
          `messages`.`name`,
          `messages`.`message`,
          `messages`.`posted_at`,
          IFNULL(GROUP_CONCAT(DISTINCT `characters`.`nickname`, '\n', `messages_recipients`.`ENo` ORDER BY `messages_recipients`.`id` SEPARATOR '\n'), '') AS `recipients`
        FROM
          `messages`
        LEFT JOIN
          `messages_recipients` ON `messages`.`id` = `messages_recipients`.`message`
        LEFT JOIN
          `characters` ON `messages_recipients`.`ENo` = `characters`.`ENo`
        WHERE
          `messages`.`RNo`     = :RNo  AND
          `messages`.`deleted` = false AND
          (
            `messages`.`id`         = :root OR
            `messages`.`refer_root` = :root
          ) AND
          `messages`.`ENo` NOT IN (
            SELECT `muted`   FROM `characters_mutes`  WHERE `muter`   = :ENo UNION ALL
            SELECT `blocked` FROM `characters_blocks` WHERE `blocker` = :ENo UNION ALL
            SELECT `blocker` FROM `characters_blocks` WHERE `blocked` = :ENo
          )
        GROUP BY
          `messages`.`id`
        ORDER BY
          `messages`.`posted_at` DESC
        LIMIT
          :offset, :number;
      ");

      $statement->bindParam(':root', $root);

      break;
  }

  $statement->bindParam(':ENo',    $_SESSION['ENo']);
  $statement->bindParam(':RNo',    $RNo);
  $statement->bindValue(':offset', $page * $GAME_CONFIG['ROOM_MESSAGES_PER_PAGE'], PDO::PARAM_INT);
  $statement->bindValue(':number', $GAME_CONFIG['ROOM_MESSAGES_PER_PAGE'] + 1,     PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  $messages = $statement->fetchAll();

  // 1件余分に取得できていれば次のページありとして余分な1件を切り捨て
  if (count($messages) == $GAME_CONFIG['ROOM_MESSAGES_PER_PAGE'] + 1) {
    $existsNext = true;
    array_pop($messages);
  } else {
  // 取得件数が足りなければ次のページなしとする
    $existsNext = false;
  }

  $icons = parseIconsResult($user['icons']);

  $PAGE_SETTING['TITLE'] = $room['administrator'] ? 'RNo.'.$RNo.' '.$room['title'] : $room['title'];

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<h1><?php if ($room['administrator']) { ?>RNo.<?=$room['RNo']?> <?php } ?><?=htmlspecialchars($room['title'])?></h1>

<section class="room-meta-info">
<?php if (!$room['official']) { // 公式トークルームでないなら詳細情報を表示 ?>
  <span>
    &lt; ENo.<?=$room['administrator']?> &gt; 最終投稿: <?=$room['last_posted_at']?>
  </span>
<?php if ($room['subscribing']) { ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input type="hidden" name="action" value="unsubscribe">
    <input type="hidden" name="room" value="<?=$roomId?>">
    <input type="hidden" name="mode" value="<?=$mode?>">

    <button class="button button-enable">購読中</button>
  </form>
<?php } else { ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
    <input type="hidden" name="action" value="subscribe">
    <input type="hidden" name="room" value="<?=$roomId?>">
    <input type="hidden" name="mode" value="<?=$mode?>">

    <button class="button">購読する</button>
  </form>
<?php 
  }
?>
<?php
}
?>
<?php if (
  ($GAME_LOGGEDIN && $room['administrator'] == $_SESSION['ENo']) || // ログインしており、トークルームの管理者が自分自身
  ($GAME_LOGGEDIN_AS_ADMINISTRATOR)                                 // あるいはゲームの管理者ならトークルーム編集ページへのリンクを表示
) { ?>
  <a href="<?=$GAME_CONFIG['URI']?>rooms/edit?room=<?=$roomId?>">
    <button class="button">設定変更</button>
  </a>
<?php } ?>
</section>

<section class="room-description">
  <?=profileDecoration($room['description'])?>
</section>

<hr>

<div id="room-reply-target-area">
返信先（クリックで返信モードを解除）
  <div id="room-reply-target-copied-dom-area" class="room-message-list room-message-list-single-item">
  </div>
</div>

<form method="post">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
  <input type="hidden" name="action" value="post">
  <input type="hidden" name="room" value="<?=$roomId?>">
  <input type="hidden" name="mode" value="<?=$mode?>">
  <input id="input-refer" type="hidden" name="refer" value="">

  <section class="room-message-editor-wrapper">
    <div>
      <span>名前</span>
      <input type="text" name="name" value="<?=htmlspecialchars($user['nickname'])?>">
      <span>アイコン</span>
      <select name="icon">
        <option value="">-- アイコンを選択 --</option>
      <?php
        $cnt = count($icons);
        for ($i = 0; $i < $cnt; $i++) {
      ?>
        <option value="<?=htmlspecialchars($icons[$i]['url'])?>"><?=$i+1?>. <?=htmlspecialchars($icons[$i]['name'])?></option>
      <?php } ?>
      </select>
    </div>
    <textarea class="room-message-editor" name="message" placeholder="メッセージ"></textarea>
    <div class="button-wrapper">
      <button class="button">送信</button>
    </div>
  </section>
</form>

<hr>

<section class="room-modelink-wrapper">
  <a class="room-modelink<?= $mode == 'all' ? ' room-modelink-current' : '" href="?room='.$roomId.'&mode=all' ?>">全体</a>
  <a class="room-modelink<?= $mode == 'rel' ? ' room-modelink-current' : '" href="?room='.$roomId.'&mode=rel' ?>">関連</a>
  <a class="room-modelink<?= $mode == 'own' ? ' room-modelink-current' : '" href="?room='.$roomId.'&mode=own' ?>">自分</a>
  <a class="room-modelink<?= $mode == 'fav' ? ' room-modelink-current' : '" href="?room='.$roomId.'&mode=fav' ?>">お気に入り</a>
</section>

<?php if (0 < $page || $existsNext) { // ボタンのうちどちらかを表示するなら ?>
<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?room=<?=$roomId?>&mode=<?=$mode?><?= $root ? '$root='.$root : '' ?>&page=<?=$page+1-1?>">前のページ</a></div>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?room=<?=$roomId?>&mode=<?=$mode?><?= $root ? '$root='.$root : '' ?>&page=<?=$page+1+1?>">次のページ</a></div>
</section>
<?php } ?>

<section class="room-message-list">
<?php
foreach ($messages as $message) {
?>
  <section class="room-message">
    <div class="room-message-icon">
    <?php
      $COMPONENT_ICON['src'] = $message['icon'];
      include GETENV('GAME_ROOT').'/components/icon.php';
    ?>
    </div>
    <div class="room-message-main">
      <?php 
        $recipients = parseRecipientsResult($message['recipients']);
        $cnt = count($recipients);
        if (0 < $cnt) { // 返信先があれば返信先表示
      ?>
      <a class="room-message-refers" href="?room=<?=$roomId?>&mode=res&root=<?=$message['refer_root']?>">
        &gt;
        <?php 
          foreach($recipients as $recipient) {
            if (2 <= $cnt && $recipient['ENo'] == $message['ENo']) { // 返信先が2件以上なら返信先＝投稿主の項目は省略
              continue;
            }
        ?>
          <?=htmlspecialchars($recipient['name'])?>(<?=$recipient['ENo']?>)
        <?php 
          }
        ?>
      </a>
      <?php
        }
      ?>
      <a class="room-message-info-link" href="<?=$GAME_CONFIG['URI']?>profile?ENo=<?=$message['ENo']?>">
        <div class="room-message-info"><?=htmlspecialchars($message['name'])?> <span class="room-message-info-eno">(ENo.<?=$message['ENo']?>)</span></div>
      </a>
      <div class="room-message-body"><?=profileDecoration($message['message'])?></div>
      <div class="room-message-details">
        <div class="room-message-detail">
          <?=$message['posted_at']?> #<?=$message['id']?>
        </div>

        <div class="room-message-actions">
        <?php
          if ($GAME_LOGGEDIN_AS_ADMINISTRATOR || $message['ENo'] == $_SESSION['ENo']) { // ゲーム管理者としてログインしている、あるいは自分の発言なら削除ボタンを表示
        ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="room" value="<?=$roomId?>">
            <input type="hidden" name="mode" value="<?=$mode?>">
            <input type="hidden" name="id" value="<?=$message['id']?>">
            <button class="room-message-action">削除</button>
          </form>
        <?php } ?>
          <button class="room-message-action room-message-action-reply" data-mno="<?=$message['id']?>">返信</button>
        </div>
      </div>
    </div>
  </section>
<?php
}
?>
</section>

<?php if (0 < $page || $existsNext) { // ボタンのうちどちらかを表示するなら ?>
<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?room=<?=$roomId?>&mode=<?=$mode?><?= $root ? '$root='.$root : '' ?>&page=<?=$page+1-1?>">前のページ</a></div>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?room=<?=$roomId?>&mode=<?=$mode?><?= $root ? '$root='.$root : '' ?>&page=<?=$page+1+1?>">次のページ</a></div>
</section>
<?php } ?>

<script>
  // 配置先を所得
  roomReplyTargetArea          = $('#room-reply-target-area');
  roomReplyTargetCopiedDomArea = $('#room-reply-target-copied-dom-area');
  inputReferDom                = $('#input-refer');

  // 配置先を隠す
  roomReplyTargetArea.hide();

  // 返信ボタンクリック時
  $('.room-message-action-reply').on('click', function(event) {
    //返信先の情報を取得
    targetDOM        = event.target;
    targetId         = $(targetDOM).attr('data-mno');
    targetMessageDOM = $(targetDOM).parents('.room-message');

    // 返信先オブジェクトをコピー
    roomReplyTargetCopiedDomArea.empty();
    roomReplyTargetCopiedDomArea.append(targetMessageDOM.clone());
    
    // リンク及びボタンを削除
    roomReplyTargetCopiedDomArea.find('a').removeAttr('href');
    roomReplyTargetCopiedDomArea.find('.room-message-actions').remove();

    // 返信先を設定
    inputReferDom.val(targetId);

    // 配置先を表示
    roomReplyTargetArea.show();
  });

  // コピーしたDOMをクリック時
  roomReplyTargetCopiedDomArea.on('click', function() {
    // 配置先を隠す
    roomReplyTargetArea.hide();

    // 返信先を削除
    inputReferDom.val('');

    // コピーしたDOMを削除
    roomReplyTargetCopiedDomArea.empty();
  });

</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>