<?php
/*
  このファイルでは主に環境変数以外のゲーム設定や表示上の設定などを設定します。
  environment.phpに書くべきかどうかは、主に「公開するレンタルサーバーと開発環境で値が変わるかどうか？」を判断基準にするとよいと思います。
  こちらでは公開するレンタルサーバーと開発環境で値が変わらないと思われるものをまとめています。
*/

//セキュリティ関連
$GAME_CONFIG['CSRF_TOKEN_LENGTH']   = 32; // 生成される固定CSRFトークンの長さを指定します。
$GAME_CONFIG['PASSWORD_MIN_LENGTH'] = 8;  // パスワードの最短の長さを指定します。
/* 以下の2つの項目は変更すると変更以前に作られたアカウントにログインが行えなくなります。本番環境ではユーザー登録が行われた後は変更しないでください。 */
$GAME_CONFIG['CLIENT_HASH_STRETCH'] = 100;            // ハッシュ化する際のストレッチ回数を指定します。
$GAME_CONFIG['CLIENT_HASH_SALT']    = 'xBjaS2EXACQy'; // ハッシュ化する際のsaltを指定します。ランダムな文字列を設定してください。注意：JavaScriptのシングルクォーテーション内に展開されます。これによりどのような影響があるかがわからない場合は記号は設定しないでください。

//セッション関連
$GAME_CONFIG['SESSION_NAME']     = 'teiki_session';      // セッション名を指定します。 
$GAME_CONFIG['SESSION_LIFETIME'] = 60 * 60 * 24 * 7 * 2; // セッション及びクッキーの有効時間を秒数で設定します。

//ユーザー設定値関連
$GAME_CONFIG['NAME_MAX_LENGTH']              = 16;  // 名前の最長の長さを指定します。
$GAME_CONFIG['NICKNAME_MAX_LENGTH']          = 8;   // 愛称の最長の長さ及びトークルームでの名前の最長の長さを指定します。
$GAME_CONFIG['CHARACTER_SUMMARY_MAX_LENGTH'] = 40;  // キャラクターのサマリーの最長の長さを指定します。
$GAME_CONFIG['TITLE_MAX_LENGTH']             = 16;  // トークルームのタイトルの最長の長さを指定します。
$GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH']      = 40;  // トークルームのサマリーの最長の長さを指定します。
$GAME_CONFIG['MESSAGE_MAX_LENGTH']           = 400; // トークルームにおけるメッセージの最長の長さを指定します。
$GAME_CONFIG['ICONS_MAX']                    = 30;  // 設定できるアイコンの最大数を指定します。

//表示関連
$GAME_CONFIG['TITLE_TEMPLATE'] = ' | Teiki Adventure'; // ページタイトルに共通でつける文言を指定します。

//表示要素数関連
$GAME_CONFIG['CHARACTER_LIST_ITEMS_PER_PAGE'] = 100; // キャラクターリストページで1ページあたりに表示するキャラクター数を指定します。
$GAME_CONFIG['ROOM_LIST_ITEMS_PER_PAGE']      = 30;  // トークルーム一覧ページで1ページあたりに表示するトークルーム数を指定します。
$GAME_CONFIG['ROOM_MESSAGES_PER_PAGE']        = 20;  // トークルームで1ページあたりに表示するメッセージ数を指定します。

//その他
$GAME_CONFIG['TOP_URI']    = $GAME_CONFIG['URI'].'';       // トップページのURIを指定します。ログアウト、登録解除した際のリダイレクト先及びログアウト状態でタイトルをクリックした際の遷移先になります。
$GAME_CONFIG['HOME_URI']   = $GAME_CONFIG['URI'].'home';   // ホームのURIを指定します。ログイン、新規登録した際のリダイレクト先及びログイン状態でタイトルをクリックした際の遷移先になります。
$GAME_CONFIG['SIGNIN_URI'] = $GAME_CONFIG['URI'].'signin'; // ログインページのURIを指定します。ログインが必要なページにログインしていない状態でアクセスした際のリダイレクト先になります。
?>