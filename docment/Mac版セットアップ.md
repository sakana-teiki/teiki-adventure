## セットアップ
以下はMac環境下でMAMPを使用して新規に環境を構築する場合の手順です。

⚠️ローカルの開発環境という前提でパスワード等をセットアップしています。本番環境では決してこれらのパスワード等を使用しないでください。

ℹ️パスワードやDB名、公開ディレクトリなど環境設定を変更した場合はteiki/configs/environment.phpの該当の値も書き換えてください。

1. 以下のソフトウェアをインストールします。
[MAMP](https://www.mamp.info/en/mac/)
[MySQL Workbench](https://www.mysql.com/jp/products/workbench/)

2. MAMPを実行し、Startをクリックしてサーバーを起動します。

3. MySQL Workbench で Mysql にログインします。
 - MySQL Workbench を起動し、MySQL Connectionsの+ボタンを押す
 - `Connection Name:` に `Localhost-root` 等入力、他はデフォルトでOK
 - パスワードを要求されるので `root` と入力
 - 今作った `Localhost-root` を押してログイン

4. （Windowsと同一）MySQL(MariaDB)のコンソールに入るので以下のコマンド群を順に実行します。MySQL(MariaDB)内にパスワードが「dbpassword」のユーザー「teiki」と、「teiki_adventure」というDBが作成され、ユーザー「teiki」にDB「teiki_adventure」に対する全権限が付与されます。

```sql
CREATE USER 'teiki' IDENTIFIED BY 'dbpassword';
CREATE DATABASE teiki_adventure;
GRANT ALL ON teiki_adventure.* TO 'teiki';
exit;
```

5. ユーザー「teiki」としてDB「teiki_adventure」に接続します。
 - 左上の家アイコンから MySQL Workbench のホーム画面に戻ります
 - MySQL Connectionsの+ボタンを押す
 - `Connection Name:` に `Localhost-teiki` 等入力
 - `Username:` に 先ほど作成したユーザー名 `teiki` を入力 他はデフォルトでOK
 - パスワードを要求されるので先ほど作成した `dbpassword` と入力
 - 今作った `Localhost-teiki` を押してログイン

6. （Windowsと同一）以下のコマンド群を実行します。

``` sql
CREATE TABLE `characters` (
	`ENo`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`password` TEXT         NOT NULL,
	`token`    TEXT         NOT NULL,
	`name`     VARCHAR(16)  NOT NULL,
	`nickname` VARCHAR(8)   NOT NULL,
	`summary`  VARCHAR(100),
	`profile`  VARCHAR(200),
	`AP`       INT UNSIGNED NOT NULL DEFAULT 0,
	`NP`       INT UNSIGNED NOT NULL DEFAULT 0,
	`ATK`      INT UNSIGNED NOT NULL DEFAULT 0,
	`DEX`      INT UNSIGNED NOT NULL DEFAULT 0,
	`MND`      INT UNSIGNED NOT NULL DEFAULT 0,
	`AGI`      INT UNSIGNED NOT NULL DEFAULT 0,
	`DEF`      INT UNSIGNED NOT NULL DEFAULT 0,
	`deleted`  BOOLEAN      NOT NULL DEFAULT false,

	PRIMARY KEY (`ENo`)
);

CREATE TABLE `characters_tags` (
	`id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`ENo` INT UNSIGNED NOT NULL,
	`tag` TEXT         NOT NULL,

	PRIMARY KEY (`id`),
	FOREIGN KEY (`ENo`) REFERENCES `characters`(`ENo`)
);

CREATE TABLE `characters_icons` (
	`id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`ENo`  INT UNSIGNED NOT NULL,
	`name` TEXT         NOT NULL,
	`url`  TEXT         NOT NULL,

	PRIMARY KEY (`id`),
	FOREIGN KEY (`ENo`) REFERENCES `characters`(`ENo`)
);

CREATE TABLE `characters_profile_images` (
	`id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`ENo` INT UNSIGNED NOT NULL,
	`url` TEXT         NOT NULL,

	PRIMARY KEY (`id`),
	FOREIGN KEY (`ENo`) REFERENCES `characters`(`ENo`)
);

CREATE TABLE `characters_favs` (
	`id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`faver` INT UNSIGNED NOT NULL,
	`faved` INT UNSIGNED NOT NULL,

	PRIMARY KEY (`id`),
	FOREIGN KEY (`faver`) REFERENCES `characters`(`ENo`),
	FOREIGN KEY (`faved`) REFERENCES `characters`(`ENo`),
	UNIQUE (`faver`, `faved`)
);

CREATE TABLE `characters_mutes` (
	`id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`muter` INT UNSIGNED NOT NULL,
	`muted` INT UNSIGNED NOT NULL,

	PRIMARY KEY (`id`),
	FOREIGN KEY (`muter`) REFERENCES `characters`(`ENo`),
	FOREIGN KEY (`muted`) REFERENCES `characters`(`ENo`),
	UNIQUE (`muter`, `muted`)
);

CREATE TABLE `characters_blocks` (
	`id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`blocker` INT UNSIGNED NOT NULL,
	`blocked` INT UNSIGNED NOT NULL,

	PRIMARY KEY (`id`),
	FOREIGN KEY (`blocker`) REFERENCES `characters`(`ENo`),
	FOREIGN KEY (`blocked`) REFERENCES `characters`(`ENo`),
	UNIQUE (`blocker`, `blocked`)
);

CREATE TABLE `rooms` (
	`RNo`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`administrator`  INT UNSIGNED,
	`title`          TEXT         NOT NULL,
	`deleted`        BOOLEAN      NOT NULL DEFAULT false,
	`summary`        TEXT         NOT NULL,
	`description`    TEXT         NOT NULL,
	`created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`last_posted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

	PRIMARY KEY (`RNo`),
	FOREIGN KEY (`administrator`) REFERENCES `characters`(`ENo`),
	INDEX (`last_posted_at`)
);

CREATE TABLE `rooms_tags` (
	`id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`RNo` INT UNSIGNED NOT NULL,
	`tag` TEXT         NOT NULL,

	PRIMARY KEY (`id`),
	FOREIGN KEY (`RNo`) REFERENCES `rooms`(`RNo`)
);

CREATE TABLE `messages` (
	`MNo`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`RNo`        INT UNSIGNED NOT NULL,
	`ENo`        INT UNSIGNED NOT NULL,
	`refer`      INT UNSIGNED,
	`refer_root` INT UNSIGNED,
	`icon`       TEXT         NOT NULL,
	`name`       TEXT         NOT NULL,
	`message`    TEXT         NOT NULL,
	`deleted`    BOOLEAN      NOT NULL DEFAULT false,
	`posted_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

	PRIMARY KEY (`MNo`),
	FOREIGN KEY (`ENo`)        REFERENCES `characters`(`ENo`),
	FOREIGN KEY (`RNo`)        REFERENCES `rooms`(`RNo`),
	FOREIGN KEY (`refer`)      REFERENCES `messages`(`MNo`),
	FOREIGN KEY (`refer_root`) REFERENCES `messages`(`MNo`),
	INDEX (`posted_at`)
);

CREATE TABLE `messages_recipients` (
	`id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`MNo` INT UNSIGNED NOT NULL,
	`ENo` INT UNSIGNED NOT NULL,

	PRIMARY KEY (`id`),
	FOREIGN KEY (`MNo`) REFERENCES `messages`(`MNo`),
	FOREIGN KEY (`ENo`) REFERENCES `characters`(`ENo`)
);

INSERT INTO `rooms` (`title`) VALUES ('全体トークルーム');
```

7. [最新のreleases](https://github.com/sakana-teiki/teiki-adventure/releases)をダウンロードして解凍し、出てきたteikiフォルダをhtdocs(MAMPの初期設定では"/Applications/MAMP/htdocs/")内に配置します。

8. teiki/.htaccessのSetEnv GAME_ROOTの値を配置したディレクトリに合わせて書き換えます(MAMPが初期設定で手順通りの場合は"/Applications/MAMP/htdocs/teiki")。
teikiから変更する場合は、configs/environment.phpの$GAME_CONFIG['URI']も合わせて変更します

9. ブラウザで[http://localhost/teiki/](http://localhost/teiki/)にアクセスします。


## うまく動かなかったら
#### ログを見る
MAMPはデフォルトで `/Applications/MAMP/logs` にPHPやMySQLのエラーログが出ているのでそれを参考に探しましょう。

#### 500エラー
たぶんSQLのエラーです。
SQL文だけ MySQL Workbench に貼り付けて試すのが楽です。
例えば、TEXT型のカラムに値を入れずにINSERTしようとしたり、
JOINしてSELECTするカラムをGROUP BYで指定しないとエラーになります。
