<?php
  require_once dirname(__DIR__).'/configs/environment.php';
  require_once dirname(__DIR__).'/configs/general.php';
  
  $GAME_PDO = new PDO('mysql:dbname='.$GAME_CONFIG['MYSQL_DBNAME'].';host='.$GAME_CONFIG['MYSQL_HOST'].':'.$GAME_CONFIG['MYSQL_PORT'], $GAME_CONFIG['MYSQL_USERNAME'], $GAME_CONFIG['MYSQL_PASSWORD']);

  // コンソールからの実行であれば確認処理を行う
  if (php_sapi_name() == 'cli') {
    echo '本当に初期化を行いますか？初期化を行う場合yesと入力してください。: ';
    $input = trim(fgets(STDIN));
    
    if ($input != 'yes') {
      echo '初期化処理が中止されました。';
      exit;
    }
  }

  // テーブルの削除
  // 外部キーの対象となっているテーブルはその外部キーの参照を行っているテーブルを削除しないと削除できないため、CREATEの時とは逆の順序で実行します。
  $statement = $GAME_PDO->prepare("
    DROP TABLE IF EXISTS
      `exploration_logs`,
      `trades`,
      `flea_markets`,
      `items_yield`,
      `announcements`,
      `threads_responses`,
      `threads`,
      `direct_messages`,
      `notifications`,
      `messages_recipients`,
      `messages`,
      `rooms_subscribers`,
      `rooms_tags`,
      `rooms`,
      `characters_results`,
      `characters_declarations`,
      `characters_items`,
      `characters_blocks`,
      `characters_mutes`,
      `characters_favs`,
      `characters_profile_images`,
      `characters_icons`,
      `characters_tags`,
      `characters`,
      `game_status`,
      `exploration_stages_master_data_drop_items`,
      `exploration_stages_master_data`,
      `items_master_data_effects`,
      `items_master_data`;
  ");

  $result = $statement->execute();

  if (!$result) {
    echo "テーブルの削除時にエラーが発生しました。";
    exit;
  }

  // テーブルの作成
  $statement = $GAME_PDO->prepare("
    CREATE TABLE `items_master_data` (
      `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `item_id`        INT UNSIGNED NOT NULL,
      `name`           TEXT         NOT NULL,
      `description`    TEXT         NOT NULL,
      `price`          INT UNSIGNED NOT NULL,
      `shop`           BOOLEAN      NOT NULL,
      `tradable`       BOOLEAN      NOT NULL,
      `usable`         BOOLEAN      NOT NULL,
      `relinquishable` BOOLEAN      NOT NULL,
      `creatable`      BOOLEAN      NOT NULL,
      `category`       ENUM('material', 'consumable') NOT NULL,
      
      PRIMARY KEY (`id`),
      UNIQUE(`item_id`)
    );

    CREATE TABLE `items_master_data_effects` (
      `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `item`   INT UNSIGNED NOT NULL,
      `effect` TEXT         NOT NULL,
      `value`  INT,

      PRIMARY KEY (`id`),
      FOREIGN KEY (`item`) REFERENCES `items_master_data`(`item_id`)
    );

    CREATE TABLE `exploration_stages_master_data` (
      `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `stage_id`             INT UNSIGNED NOT NULL,
      `complete_requirement` INT UNSIGNED NOT NULL,
      `requirement_stage_id` INT UNSIGNED,
      `title`                TEXT         NOT NULL,
      `text`                 TEXT         NOT NULL,

      PRIMARY KEY (`id`),
      UNIQUE(`stage_id`)
    );

    CREATE TABLE `exploration_stages_master_data_drop_items` (
      `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `stage`            INT UNSIGNED NOT NULL,
      `item`             INT UNSIGNED NOT NULL,
      `rate_numerator`   INT UNSIGNED NOT NULL,
      `rate_denominator` INT UNSIGNED NOT NULL,

      PRIMARY KEY (`id`),
      FOREIGN KEY (`item`)  REFERENCES `items_master_data`(`item_id`),
      FOREIGN KEY (`stage`) REFERENCES `exploration_stages_master_data`(`stage_id`)
    );

    CREATE TABLE `game_status` (
      `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `distributing_ap` BOOLEAN      NOT NULL,
      `maintenance`     BOOLEAN      NOT NULL,
      `update_status`   BOOLEAN      NOT NULL,
      `next_update_nth` INT UNSIGNED NOT NULL,
      `AP`              INT UNSIGNED NOT NULL,

      PRIMARY KEY (`id`)
    );

    CREATE TABLE `characters` (
      `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `ENo`              INT,
      `password`         TEXT         NOT NULL,
      `token`            TEXT         NOT NULL,
      `name`             TEXT         NOT NULL,
      `nickname`         TEXT         NOT NULL,
      `summary`          TEXT         NOT NULL,
      `profile`          TEXT         NOT NULL,
      `webhook`          TEXT         NOT NULL,
      `consumedAP`       INT UNSIGNED NOT NULL DEFAULT 0,
      `NP`               INT UNSIGNED NOT NULL DEFAULT 0,
      `ATK`              INT UNSIGNED NOT NULL DEFAULT 0,
      `DEX`              INT UNSIGNED NOT NULL DEFAULT 0,
      `MND`              INT UNSIGNED NOT NULL DEFAULT 0,
      `AGI`              INT UNSIGNED NOT NULL DEFAULT 0,
      `DEF`              INT UNSIGNED NOT NULL DEFAULT 0,
      `money`            INT UNSIGNED NOT NULL DEFAULT 0,
      `additional_icons` INT UNSIGNED NOT NULL DEFAULT 0,
      `deleted`          BOOLEAN      NOT NULL DEFAULT false,
      `administrator`    BOOLEAN      NOT NULL DEFAULT false,
      `notification_replied`                BOOLEAN   NOT NULL DEFAULT true,
      `notification_new_arrival`            BOOLEAN   NOT NULL DEFAULT true,
      `notification_faved`                  BOOLEAN   NOT NULL DEFAULT true,
      `notification_direct_message`         BOOLEAN   NOT NULL DEFAULT true,
      `notification_webhook_replied`        BOOLEAN   NOT NULL DEFAULT true,
      `notification_webhook_new_arrival`    BOOLEAN   NOT NULL DEFAULT true,
      `notification_webhook_faved`          BOOLEAN   NOT NULL DEFAULT true,
      `notification_webhook_direct_message` BOOLEAN   NOT NULL DEFAULT true,
      `notifications_last_checked_at`    TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00',

      PRIMARY KEY (`id`),
      UNIQUE(`ENo`)
    );
    
    CREATE TABLE `characters_tags` (
      `id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `ENo` INT          NOT NULL,
      `tag` TEXT         NOT NULL,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`ENo`) REFERENCES `characters`(`ENo`)
    );
    
    CREATE TABLE `characters_icons` (
      `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `ENo`  INT          NOT NULL,
      `name` TEXT         NOT NULL,
      `url`  TEXT         NOT NULL,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`ENo`) REFERENCES `characters`(`ENo`)
    );
    
    CREATE TABLE `characters_profile_images` (
      `id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `ENo` INT          NOT NULL,
      `url` TEXT         NOT NULL,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`ENo`) REFERENCES `characters`(`ENo`)
    );
    
    CREATE TABLE `characters_favs` (
      `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `faver` INT          NOT NULL,
      `faved` INT          NOT NULL,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`faver`) REFERENCES `characters`(`ENo`),
      FOREIGN KEY (`faved`) REFERENCES `characters`(`ENo`),
      UNIQUE (`faver`, `faved`)
    );
    
    CREATE TABLE `characters_mutes` (
      `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `muter` INT          NOT NULL,
      `muted` INT          NOT NULL,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`muter`) REFERENCES `characters`(`ENo`),
      FOREIGN KEY (`muted`) REFERENCES `characters`(`ENo`),
      UNIQUE (`muter`, `muted`)
    );
    
    CREATE TABLE `characters_blocks` (
      `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `blocker` INT          NOT NULL,
      `blocked` INT          NOT NULL,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`blocker`) REFERENCES `characters`(`ENo`),
      FOREIGN KEY (`blocked`) REFERENCES `characters`(`ENo`),
      UNIQUE (`blocker`, `blocked`)
    );

    CREATE TABLE `characters_items` (
      `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `ENo`    INT          NOT NULL,
      `item`   INT UNSIGNED NOT NULL,
      `number` INT UNSIGNED NOT NULL,
      
      PRIMARY KEY (`id`),
      FOREIGN KEY (`item`) REFERENCES `items_master_data`(`item_id`),
      UNIQUE (`ENo`, `item`)
    );

    CREATE TABLE `characters_declarations` (
      `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `ENo`   INT          NOT NULL,
      `nth`   INT UNSIGNED NOT NULL,
      `diary` TEXT         NOT NULL,

      PRIMARY KEY (`id`)
    );

    CREATE TABLE `characters_results` (
      `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `ENo`   INT          NOT NULL,
      `nth`   INT UNSIGNED NOT NULL,

      PRIMARY KEY (`id`)
    );
    
    CREATE TABLE `rooms` (
      `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `RNo`            INT,
      `administrator`  INT,
      `title`          TEXT         NOT NULL,
      `summary`        TEXT         NOT NULL,
      `description`    TEXT         NOT NULL,
      `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `last_posted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `deleted`        BOOLEAN      NOT NULL DEFAULT false,
      `official`       BOOLEAN      NOT NULL DEFAULT false,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`administrator`) REFERENCES `characters`(`ENo`),
      UNIQUE(`RNo`),
      INDEX (`last_posted_at`)
    );
    
    CREATE TABLE `rooms_tags` (
      `id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `RNo` INT          NOT NULL,
      `tag` TEXT         NOT NULL,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`RNo`) REFERENCES `rooms`(`RNo`)
    );

    CREATE TABLE `rooms_subscribers` (
      `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `RNo`        INT          NOT NULL,
      `subscriber` INT          NOT NULL,

      PRIMARY KEY (`id`),
      FOREIGN KEY (`RNo`)        REFERENCES `rooms`(`RNo`),
      FOREIGN KEY (`subscriber`) REFERENCES `characters`(`ENo`)
    );
    
    CREATE TABLE `messages` (
      `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `RNo`        INT          NOT NULL,
      `ENo`        INT          NOT NULL,
      `refer`      INT UNSIGNED,
      `refer_root` INT UNSIGNED,
      `icon`       TEXT         NOT NULL,
      `name`       TEXT         NOT NULL,
      `message`    TEXT         NOT NULL,
      `deleted`    BOOLEAN      NOT NULL DEFAULT false,
      `posted_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`ENo`)        REFERENCES `characters`(`ENo`),
      FOREIGN KEY (`RNo`)        REFERENCES `rooms`(`RNo`),
      FOREIGN KEY (`refer`)      REFERENCES `messages`(`id`),
      FOREIGN KEY (`refer_root`) REFERENCES `messages`(`id`),
      INDEX (`posted_at`)
    );
    
    CREATE TABLE `messages_recipients` (
      `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `message` INT UNSIGNED NOT NULL,
      `ENo`     INT          NOT NULL,
    
      PRIMARY KEY (`id`),
      FOREIGN KEY (`message`) REFERENCES `messages`(`id`),
      FOREIGN KEY (`ENo`)     REFERENCES `characters`(`ENo`)
    );

    CREATE TABLE `notifications` (
      `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `ENo`            INT,
      `type`           ENUM('announcement', 'administrator', 'replied', 'new_arrival', 'faved', 'direct_message') NOT NULL,
      `target`         INT,
      `count`          INT,
      `message`        TEXT      NOT NULL,
      `notificated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

      PRIMARY KEY (`id`),
      FOREIGN KEY (`ENo`) REFERENCES `characters`(`ENo`),
      INDEX (`notificated_at`)
    );

    CREATE TABLE `direct_messages` (
      `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `from`      INT          NOT NULL,
      `to`        INT          NOT NULL,
      `message`   TEXT         NOT NULL,
      `sended_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

      PRIMARY KEY (`id`),
      FOREIGN KEY (`from`) REFERENCES `characters`(`ENo`),
      FOREIGN KEY (`to`)   REFERENCES `characters`(`ENo`),
      INDEX (`from`),
      INDEX (`to`),
      INDEX (`sended_at`)
    );

    CREATE TABLE `threads` (
      `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `title`          TEXT         NOT NULL,
      `name`           TEXT         NOT NULL,
      `identifier`     TEXT         NOT NULL,
      `message`        TEXT         NOT NULL,
      `secret`         TEXT         NOT NULL,
      `password`       TEXT         NOT NULL,
      `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `last_posted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `administrator`  BOOLEAN      NOT NULL DEFAULT false,
      `board` ENUM('community', 'trade', 'bug') NOT NULL,
      `state` ENUM('open', 'closed', 'deleted') NOT NULL DEFAULT 'open',

      PRIMARY KEY (`id`),
      INDEX (`last_posted_at`),
      INDEX (`board`),
      INDEX (`state`)
    );

    CREATE TABLE `threads_responses` (
      `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `thread`         INT UNSIGNED NOT NULL,
      `name`           TEXT         NOT NULL,
      `identifier`     TEXT         NOT NULL,
      `message`        TEXT         NOT NULL,
      `secret`         TEXT         NOT NULL,
      `password`       TEXT         NOT NULL,
      `deleted`        BOOLEAN      NOT NULL DEFAULT false,
      `administrator`  BOOLEAN      NOT NULL DEFAULT false,
      `posted_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

      PRIMARY KEY (`id`),
      FOREIGN KEY (`thread`) REFERENCES `threads`(`id`)
    );

    CREATE TABLE `announcements` (
      `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `title`        TEXT         NOT NULL,
      `message`      TEXT         NOT NULL,
      `announced_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

      PRIMARY KEY (`id`),
      INDEX (`announced_at`)
    );

    CREATE TABLE `items_yield` (
      `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `item`  INT UNSIGNED NOT NULL,
      `yield` INT UNSIGNED NOT NULL,

      PRIMARY KEY (`id`),
      FOREIGN KEY (`item`) REFERENCES `items_master_data`(`item_id`)
    );

    CREATE TABLE `flea_markets` (
      `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `seller`             INT          NOT NULL,
      `buyer`              INT,
      `sell_item`          INT UNSIGNED,
      `sell_item_number`   INT UNSIGNED NOT NULL,
      `demand_item`        INT UNSIGNED,
      `demand_item_number` INT UNSIGNED NOT NULL,
      `state` ENUM('sale', 'sold', 'cancelled') NOT NULL DEFAULT 'sale',
      
      PRIMARY KEY (`id`),
      FOREIGN KEY (`sell_item`)   REFERENCES `items_master_data`(`item_id`),
      FOREIGN KEY (`demand_item`) REFERENCES `items_master_data`(`item_id`)
    );

    CREATE TABLE `trades` (
      `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `master`      INT          NOT NULL,
      `target`      INT          NOT NULL,
      `item`        INT UNSIGNED,
      `item_number` INT UNSIGNED NOT NULL,
      `state` ENUM('trading', 'finished', 'cancelled_by_master', 'cancelled_by_target') NOT NULL,
      
      PRIMARY KEY (`id`),
      FOREIGN KEY (`item`) REFERENCES `items_master_data`(`item_id`)
    );

    CREATE TABLE `exploration_logs` (
      `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `leader`    INT          NOT NULL,
      `stage`     INT UNSIGNED NOT NULL,
      `timestamp` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      
      PRIMARY KEY (`id`),
      FOREIGN KEY (`leader`) REFERENCES `characters`(`ENo`),
      FOREIGN KEY (`stage`)  REFERENCES `exploration_stages_master_data`(`stage_id`)
    );
  ");

  $result = $statement->execute();

  if (!$result) {
    echo "テーブルの作成時にエラーが発生しました。";
    exit;
  }

  // ゲームステータスの作成
  // 非メンテナンス中、自動AP配布を行わない、前回結果確定済、次回更新回数1、配布AP量0
  $statement = $GAME_PDO->prepare("
    INSERT INTO `game_status` (
      `maintenance`,
      `distributing_ap`,
      `update_status`,
      `next_update_nth`,
      `AP`
    ) VALUES (
      false,
      false,
      true,
      1,
      0
    );
  ");

  $result = $statement->execute();

  if (!$result) {
    echo "ゲームステータスの作成時にエラーが発生しました。";
    exit;
  }

  // 管理者アカウント作成
  foreach($GAME_CONFIG['INITIAL_ADMINISTRATORS'] as $admin){
    // クライアント側のパスワードハッシュ化と同様のハッシュ化処理
    $password = $admin['password'].$GAME_CONFIG['CLIENT_HASH_SALT']; // ソルティング
    for ($i = 0; $i < $GAME_CONFIG['CLIENT_HASH_STRETCH']; $i++) { // ストレッチング
      $password = hash('sha256', $password);
    }

    // パスワードのサーバー側ハッシュ化
    $password = password_hash($password, PASSWORD_DEFAULT);

    // CSRFトークンの生成
    $token = bin2hex(openssl_random_pseudo_bytes($GAME_CONFIG['CSRF_TOKEN_LENGTH']));

    $statement = $GAME_PDO->prepare("
      INSERT INTO `characters` (
        `ENo`,
        `name`,
        `nickname`,
        `password`,
        `token`,
        `summary`,
        `profile`,
        `webhook`,
        `administrator`
      ) VALUES (
        :ENo,
        :name,
        :nickname,
        :password,
        :token,
        '',
        '',
        '',
        true
      );
    ");

    $statement->bindParam(':ENo',      $admin['ENo']);
    $statement->bindParam(':name',     $admin['name']);
    $statement->bindParam(':nickname', $admin['nickname']);
    $statement->bindParam(':password', $password);
    $statement->bindParam(':token',    $token);

    $result = $statement->execute();

    if (!$result) {
      echo "管理者アカウントの作成時にエラーが発生しました。";
      exit;
    }
  }

  // 公式トークルーム作成
  foreach($GAME_CONFIG['PUBLIC_ROOMS'] as $room){
    $statement = $GAME_PDO->prepare("
      INSERT INTO `rooms` (
        `RNo`,
        `title`,
        `summary`,
        `description`,
        `official`
      ) VALUES (
        :RNo,
        :title,
        :summary,
        :description,
        true
      );
    ");

    $statement->bindParam(':RNo',         $room['RNo']);
    $statement->bindParam(':title',       $room['title']);
    $statement->bindParam(':summary',     $room['summary']);
    $statement->bindParam(':description', $room['description']);

    $result = $statement->execute();

    if (!$result) {
      echo "公式トークルームの作成時にエラーが発生しました。";
      exit;
    }
  }

  // セッションの破棄
  // セッションファイル一覧を削除
  $sessionFiles = glob(dirname(__DIR__).'/sessions/sess_*');
  
  if ($sessionFiles === false) {
    echo "セッションファイル一覧の取得時にエラーが発生しました。";
    exit;
  }

  // セッションファイルを削除
  foreach($sessionFiles as $file) {
    $result = unlink($file);

    if (!$result) {
      echo "セッションファイルの削除時にエラーが発生しました。";
      exit;
    }
  }

  // マスタデータの読み込み
  require_once dirname(__DIR__).'/actions/import_master.php';

  echo "初期化が完了しました。";
?>