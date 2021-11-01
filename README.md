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
- 公式トークルーム
- 個別トークルーム
- トークルーム作成
- トークルーム編集
- トークルーム一覧
- キャラクターお気に入り
- キャラクターミュート
- キャラクターブロック
- お知らせ
- 通知（サイト内・Discord）
- ダイレクトメッセージ
- 掲示板
- 管理者キャラクター
- コントロールパネル

## 諸注意
このプロジェクトはまだ開発中です。足りない機能が多く、不具合が含まれている可能性が高いです。

このプロジェクトはMIT Licenseの下で提供されているため、このプロジェクトを利用する場合MIT Licenseの定めに従う必要があります。条文についてはLICENSEをご確認ください。なお、Open Source Group JapanによるMIT Licenseの日本語訳は[こちら](https://licenses.opensource.jp/MIT/MIT.html)より参照できます。あくまで日本語訳は参考であるという点に留意してください。

また、このプロジェクトではjQuery、jsSHA、Normalize.cssというプロジェクトを利用しています。このプロジェクトの該当部分についてそれらを利用しない形に変更しない場合、それらのプロジェクトのライセンスにも従う必要があります。

## セットアップ
以下はWindows環境下でXAMPPを使用して新規に環境を構築する場合の手順です。Mac環境下でMAMPを使用する場合は[こちら](https://github.com/sakana-teiki/teiki-adventure/blob/master/documents/Mac%E7%89%88%E3%82%BB%E3%83%83%E3%83%88%E3%82%A2%E3%83%83%E3%83%97.md)を参照してください。

⚠️ローカルの開発環境という前提でパスワード等をセットアップしています。本番環境では決してこれらのパスワード等を使用しないでください。

ℹ️このプロジェクトを利用したサイトを公開する前にはdocuments/公開前のチェックリスト.mdを確認してください。

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

5. [最新のreleases](https://github.com/sakana-teiki/teiki-adventure/releases)をダウンロードして解凍し、出てきたteikiフォルダをhtdocs(XAMPPの初期設定では"C:\xampp\htdocs")内に配置します。

6. teiki/.htaccessのSetEnv GAME_ROOTの値を配置したディレクトリに合わせて書き換えます(XAMPPが初期設定で手順通りの場合は"C:\xampp\htdocs\teiki")。

7. ブラウザで[http://localhost/teiki/control-panel/initialize](http://localhost/teiki/control-panel/initialize)にアクセスし、表示されたサイトで`init`を入力して「データ初期化」をクリックします。

## Q&A

### Q. コードのこの部分がよく分かりません。
以下のいずれかまたはissueまでどうぞ。あるいは別の連絡先を持っている場合はそちらでも構いません。ある程度はサポートします。

|サービス|アカウント|
| --- | --- |
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

### Q. さくらのレンタルサーバ等で、Failed to open stream: No such file or directory...のようなエラーが出る。
さくらのレンタルサーバ等、一部のレンタルサーバでは.htaccess記載のSetEnvの値が無効になります。各phpファイルの「GETENV('GAME_ROOT').」を「$_SERVER['DOCUMENT_ROOT'].'/teiki'.」に置換するなどで対応を行ってください。

なお、$_SERVER['DOCUMENT_ROOT']の値もレンタルサーバによっては適切な値が取得できないことがあります。その場合も適宜対応を行ってください。対応方法が分からない等あればお問い合わせください。

## ライセンス
MIT License

ただしプロジェクト内に別のオープンソースプロジェクトの成果物を含んでおり、それらのライセンスに関しては別途参照する必要があります。概要は以下のとおりです。

| OSS | ライセンス | ディレクトリ |
| --- | --- | --- |
|jQuery|MIT License|static/scripts/jquery-3.6.0.min.js|
|jsSHA|BSD-3-Clause License|static/scripts/jssha-sha256.js|
|Normalize.css|MIT License|static/styles/normalize.css|
