> 定期・APゲームと呼ばれるブラウザゲームの共通部分を作りやすくすることを目的に作成された定期・APゲームのサンプルです。現在は開発中の段階になります。

## 基本方針
- 初心者/レンタルサーバー環境を想定して開発する。
- 環境はPHP+MySQL(MariaDB)+Apacheとする。
- 初心者にとって難易度の高い技術や設計(MVCモデル等)を使用しない。
- 環境構築が複雑な技術(Composer等)を使用しない。ほぼXAMPP環境に入れるだけで動作するようにする。
- 初心者でも読みやすいコードを書く(簡略だが習熟していなければ分かりづらいコードの書き方を避ける)。
- jQuery/CSSリセットを使用する。その他の前提リソースは必然性がなければ導入しない。

## 現在実装されている機能/ページ群
- キャラクター登録
- ログイン
- キャラクター一覧
- キャラクター削除
- キャラクター設定
- 全体トークルーム
- 個別トークルーム
- トークルーム作成
- トークルーム編集
- トークルーム一覧
- キャラクターお気に入り
- キャラクターミュート
- キャラクターブロック

## 諸注意
このプロジェクトはまだ開発中です。足りない機能が多く、不具合が含まれている可能性が高いです。

このプロジェクトはMIT Licenseの下で提供されているため、このプロジェクトを利用する場合MIT Licenseの定めに従う必要があります。条文についてはLICENSEをご確認ください。なお、Open Source Group JapanによるMIT Licenseの日本語訳は[こちら](https://licenses.opensource.jp/MIT/MIT.html)より参照できます。あくまで日本語訳は参考であるという点に留意してください。

また、このプロジェクトではjQuery、jsSHA、Normalize.cssというプロジェクトを利用しています。このプロジェクトの該当部分についてそれらを利用しない形に変更しない場合、それらのプロジェクトのライセンスにも従う必要があります。

## セットアップ
以下はWindows環境下でXAMPPを使用して新規に環境を構築する場合の手順です。

⚠️ローカルの開発環境という前提でパスワード等をセットアップしています。本番環境では決してこれらのパスワード等を使用しないでください。

ℹ️パスワードやDB名、公開ディレクトリなど環境設定を変更した場合はteiki/configs/environment.phpの該当の値も書き換えてください。

1. XAMPPをインストールしWindowsを再起動します。

2. XAMPPを実行し、ApacheとMySQLのStartを実行します。

3. XAMPP右のメニューからShellを起動し、以下のコマンドを入力します。入力後パスワードを訊かれるので、何も入力せずエンターを押します。

```sh
mysql --user=root --password
```

4. MySQL(MariaDB)のコンソールに入るので以下のコマンド群を順に実行します。MySQL(MariaDB)内にパスワードが「dbpassword」のユーザー「teiki」と、「teiki_adventure」というDBが作成され、ユーザー「teiki」にDB「teiki_adventure」に対する全権限が付与されます。

```sql
CREATE USER 'teiki' IDENTIFIED BY 'dbpassword';
CREATE DATABASE teiki_adventure;
GRANT ALL ON teiki_adventure.* TO 'teiki';
exit;
```

5. 以下のコマンドを入力して再びMySQL(MariaDB)のコンソールに入ります。ユーザー「teiki」としてDB「teiki_adventure」に接続します。

```sh
mysql --user=teiki --password=dbpassword --database=teiki_adventure
```

6. 以下のコマンド群を実行します。

``` sql
CREATE TABLE `characters` (
	`ENo`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`password` TEXT         NOT NULL,
	`token`    TEXT         NOT NULL,
	`name`     TEXT         NOT NULL,
	`nickname` TEXT         NOT NULL,
	`summary`  TEXT         NOT NULL,
	`profile`  TEXT         NOT NULL,
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

7. releasesから最新の◆◆◆をダウンロードして解凍し、出てきたteikiフォルダをhtdocs(XAMPPの初期設定では"C:\xampp\htdocs")内に配置します。

8. teiki/.htaccessのSetEnv GAME_ROOTの値を配置したディレクトリに合わせて書き換えます(XAMPPが初期設定で手順通りの場合は"C:\xampp\htdocs\teiki")。

9. ブラウザで[http://localhost/teiki/](http://localhost/teiki/)にアクセスします。

## 各ディレクトリ/ファイルの役割
この項目では初期で各ディレクトリ及びファイルがどのような役割になっているかを解説します。

### actions
AP配布やデータ初期化など、ブラウザ以外(cron, コンソール等)から起動することを想定したphpファイルを配置します。まだ開発中のため、現段階では何も入っていません。

### components
ページの部品となるphpファイルを配置します。値を必要とする場合、$COMPONENTS_コンポーネント名['属性名']に代入して受け渡すようにします(例：icon.php)。header.phpとfooter.phpについては例外的に$PAGE_SETTING['属性名']で値を受け取ります。

### configs
設定を行うためのphpファイルを配置します。主に環境設定に関する設定値はenvironment.phpに、そうでない設定値はgeneral.phpに記載します。

### middlewares
各ページの動作に必要な初期動作をまとめたphpファイルを配置します。各ページの最上部にてrequireして使用します。それぞれ以下の機能を持ちます。

|initialize.php|設定値の読み込み、PDOへの接続やセッションの開始などの初期動作を行います。このファイルは必ずどのmidddlewareより先に、かつ必ず読み込む必要があります。|
|verification.php|認証を行います。GETリクエストの場合はログインチェックを行いログインしていなければログインページへ、POSTリクエストの場合はログインチェックに加えてCSRFトークンの検証を行いNGであれば403(Forbidden)を返します。PUT, DELETEリクエストに関しては必ず405(Method Not Allowed)を返します。|

### pages
各ページのphpファイルを配置します。このディレクトリに配置したphpファイルは例えば「`http://example.com/teiki/`」で公開する場合、pages/home.phpであればURLは「`http://example.com/teiki/home`」、pages/hoge/index.phpであればURLは「`http://example.com/teiki/hoge/`」でアクセスできるようになります。

ページの構造について、詳しくはpages/_template.phpを参照してください。

### static
画像やスクリプトなど、静的なファイルを配置します。このディレクトリに配置したファイルは例えば「`http://example.com/teiki/`」で公開する場合、static/favicon.icoであればURLは「`http://example.com/teiki/favicon.ico`」、static/styles/theme.cssであればURLは「`http://example.com/teiki/styles/theme.css`」でアクセスできます。

現在、index.htm、index.htmlを配置しても/で終わるURLによってアクセスできないという既知の不具合があります。

### utils
複数のphpファイルで使用する関数をまとめます。

### .gitignore
Gitを利用する際に追跡を行わないファイルを指定します。Gitを利用しない場合は不要です。

### .htaccess
アクセス制御を行うためのファイルです。このファイルでpages/とstatic/への転送設定を行っています。

### LICENSE
このプロジェクトの頒布条件を記載したファイルです。

### README&#046;md
このファイルです。動作には不要なため、削除しても構いません。

## Q&A

### Q. コードのこの部分がよく分かりません。
以下のいずれかまたはissueまでどうぞ。あるいは別の連絡先を持っている場合はそちらでも構いません。ある程度はサポートします。

|Twitter|@sakana_public|
|Discord|( 'ω'　　)＜)#8186|

### Q. 不具合・要望があります。
プルリクエストやissueからどうぞ。難しい場合は上の開発者連絡先に送っていただいても構いません。

### Q. ある機能は不要なので削りたいが、どこを消せばいいのか分からない。
不明な場合、該当の機能を提供しているページを削除するだけでも構わないと思います。使わない機能がDBやコード内に残っているのが気になる、残っていることで開発したい機能を作る上で支障がある等の場合はお問い合わせください。

### Q. コードが全体としてどういう流れになっているのか分からない。
おおよそpages内のphpを起点にして動作しているので、pages内のファイルから動作を辿ると全体の動きが分かりやすいと思います。pages/_template.phpも参照してください。また、全てのアクセスは.htaccessの設定に従って処理されているため、必要な場合はそちらも参照してください。

### Q. プロジェクトが放置されている/方針が一部合わないetc...なのですが、勝手に引き継いで開発・公開しても構いませんか？
構いません。本プロジェクトはMIT Licenseで公開されているため、その定めに従う限り自由です。

### Q. URLの長いアイコンをいっぱい登録したらアイコンが一部途切れる/出てこない。
MySQLのGROUP_CONCATの最大長に引っかかっていると思われます。group_concat_max_lenの設定を十分な長さに変更してください。

## ライセンス
MIT License

ただしプロジェクト内に別のオープンソースプロジェクトの成果物を含んでおり、それらのライセンスに関しては別途参照する必要があります。概要は以下のとおりです。

|jQuery|MIT License|static/scripts/jquery-3.6.0.min.js|
|jsSHA|BSD-3-Clause License|static/scripts/jssha-sha256.js|
|Normalize.css|MIT License|static/styles/normalize.css|