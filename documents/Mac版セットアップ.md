## セットアップ
以下はMac環境下でMAMPを使用して新規に環境を構築する場合の手順です。

⚠️ローカルの開発環境という前提でパスワード等をセットアップしています。本番環境では決してこれらのパスワード等を使用しないでください。

ℹ️このプロジェクトを利用したサイトを公開する前にはdocuments/公開前のチェックリスト.mdを確認してください。

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

6. [最新のreleases](https://github.com/sakana-teiki/teiki-adventure/releases)をダウンロードして解凍し、出てきたteikiフォルダをhtdocs(MAMPの初期設定では"/Applications/MAMP/htdocs/")内に配置します。

7. teiki/.htaccessのSetEnv GAME_ROOTの値を配置したディレクトリに合わせて書き換えます(MAMPが初期設定で手順通りの場合は"/Applications/MAMP/htdocs/teiki")。
teikiから変更する場合は、configs/environment.phpの$GAME_CONFIG['URI']も合わせて変更します。

8. ブラウザで[http://localhost/teiki/control-panel/initialize](http://localhost/teiki/control-panel/initialize)にアクセスし、表示されたサイトで`init`を入力して「データ初期化」をクリックします。

## うまく動かなかったら
#### ログを見る
MAMPはデフォルトで `/Applications/MAMP/logs` にPHPやMySQLのエラーログが出ているのでそれを参考に探しましょう。

#### 500エラー
たぶんSQLのエラーです。
SQL文だけ MySQL Workbench に貼り付けて試すのが楽です。
例えば、TEXT型のカラムに値を入れずにINSERTしようとしたり、
JOINしてSELECTするカラムをGROUP BYで指定しないとエラーになります。
