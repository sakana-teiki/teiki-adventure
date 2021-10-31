<?php
  require_once GETENV('GAME_ROOT').'/configs/environment.php';
  require_once GETENV('GAME_ROOT').'/configs/general.php';
  
  $GAME_PDO = new PDO('mysql:dbname='.$GAME_CONFIG['MYSQL_DBNAME'].';host='.$GAME_CONFIG['MYSQL_HOST'].':'.$GAME_CONFIG['MYSQL_PORT'], $GAME_CONFIG['MYSQL_USERNAME'], $GAME_CONFIG['MYSQL_PASSWORD']);

  // $ACTION_INITIALIZE['skip_confirm']がtrueでなければ確認処理を行う
  if (!(isset($ACTION_INITIALIZE['skip_confirm']) && $ACTION_INITIALIZE['skip_confirm'])) {
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
      `characters_blocks`,
      `characters_mutes`,
      `characters_favs`,
      `characters_profile_images`,
      `characters_icons`,
      `characters_tags`,
      `characters`;
  ");

  $result = $statement->execute();

  if (!$result) {
    echo "テーブルの削除時にエラーが発生しました。";
    exit;
  }

  // テーブルの削除
  $statement = $GAME_PDO->prepare("
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
      `AP`               INT UNSIGNED NOT NULL DEFAULT 0,
      `NP`               INT UNSIGNED NOT NULL DEFAULT 0,
      `ATK`              INT UNSIGNED NOT NULL DEFAULT 0,
      `DEX`              INT UNSIGNED NOT NULL DEFAULT 0,
      `MND`              INT UNSIGNED NOT NULL DEFAULT 0,
      `AGI`              INT UNSIGNED NOT NULL DEFAULT 0,
      `DEF`              INT UNSIGNED NOT NULL DEFAULT 0,
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
      `board` ENUM('community', 'bug')          NOT NULL,
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
  ");

  $result = $statement->execute();

  if (!$result) {
    echo "テーブルの作成時にエラーが発生しました。";
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
  
  echo "初期化が完了しました。";
  echo "セッションデータは残留しているため、必要な場合はセッションの削除も行ってください。";
?>