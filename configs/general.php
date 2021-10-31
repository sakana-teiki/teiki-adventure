<?php
/*
  このファイルでは主に環境変数以外のゲーム設定や表示上の設定などを設定します。
  environment.phpに書くべきかどうかは、主に「公開するレンタルサーバーと開発環境で値が変わるかどうか？」を判断基準にするとよいと思います。
  こちらでは公開するレンタルサーバーと開発環境で値が変わらないと思われるものをまとめています。
*/

//セキュリティ関連
$GAME_CONFIG['CSRF_TOKEN_LENGTH']   = 32;             // 生成される固定CSRFトークンの長さを指定します。
$GAME_CONFIG['PASSWORD_MIN_LENGTH'] = 8;              // パスワードの最短の長さを指定します。
$GAME_CONFIG['IDENTIFIER_SECRET']   = 'ID]mbjE>1S|y'; // スレッドの発言IDの生成用です。本番環境では別の十分な強度の文字列に変更してください。
$GAME_CONFIG['IDENTIFIER_LENGTH']   = 8;              // スレッドの発言IDの文字数です。
/* 以下の2つの項目は変更すると変更以前に作られたアカウントにログインが行えなくなります。本番環境では初期化の前に変更を行い、以降は変更しないでください。 */
$GAME_CONFIG['CLIENT_HASH_STRETCH'] = 100;            // ハッシュ化する際のストレッチ回数を指定します。
$GAME_CONFIG['CLIENT_HASH_SALT']    = 'xBjaS2EXACQy'; // ハッシュ化する際のsaltを指定します。本番環境では別の十分な強度の文字列に変更してください。注意：JavaScriptのシングルクォーテーション内に展開されます。これによりどのような影響があるかがわからない場合は記号は設定しないでください。

//セッション関連
$GAME_CONFIG['SESSION_NAME']     = 'teiki_session';      // セッション名を指定します。 
$GAME_CONFIG['SESSION_LIFETIME'] = 60 * 60 * 24 * 7 * 2; // セッション及びクッキーの有効時間を秒数で設定します。

//ユーザー入力値関連
$GAME_CONFIG['CHARACTER_NAME_MAX_LENGTH']     = 16;   // 名前の最長の長さを指定します。
$GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH'] = 8;    // 愛称の最長の長さ及びトークルームでの名前の最長の長さを指定します。
$GAME_CONFIG['CHARACTER_SUMMARY_MAX_LENGTH']  = 40;   // キャラクターのサマリーの最長の長さを指定します。
$GAME_CONFIG['CHARACTER_PROFILE_MAX_LENGTH']  = 2000; // キャラクターのプロフィールの最長の長さを指定します。
$GAME_CONFIG['CHARACTER_TAG_MAX']             = 16;   // キャラクターのタグの数の最大を指定します。
$GAME_CONFIG['CHARACTER_TAG_MAX_LENGTH']      = 16;   // キャラクターのタグの最大の長さを指定します。
$GAME_CONFIG['CHARACTER_ICON_MAX']            = 30;   // キャラクターの設定できるアイコンの最大数を指定します。
$GAME_CONFIG['ROOM_TITLE_MAX_LENGTH']         = 16;   // トークルームのタイトルの最長の長さを指定します。
$GAME_CONFIG['ROOM_SUMMARY_MAX_LENGTH']       = 40;   // トークルームのサマリーの最長の長さを指定します。
$GAME_CONFIG['ROOM_DESCRIPTION_MAX_LENGTH']   = 2000; // トークルームの説明文の最長の長さを指定します。
$GAME_CONFIG['ROOM_MESSAGE_MAX_LENGTH']       = 400;  // トークルームのメッセージの最長の長さを指定します。
$GAME_CONFIG['ROOM_TAG_MAX']                  = 16;   // トークルームのタグの数の最大を指定します。
$GAME_CONFIG['ROOM_TAG_MAX_LENGTH']           = 16;   // トークルームのタグの最大の長さを指定します。
$GAME_CONFIG['DIRECT_MESSEAGE_MAX_LENGTH']    = 400;  // ダイレクトメッセージの最大の長さを指定します。
$GAME_CONFIG['THREAD_TITLE_MAX_LENGTH']       = 32;   // スレッド・スレッド投稿のタイトルの最長の長さを指定します。
$GAME_CONFIG['THREAD_NAME_MAX_LENGTH']        = 32;   // スレッド・スレッド投稿の投稿者名の最長の長さを指定します。
$GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH']     = 2000; // スレッド・スレッド投稿の本文の最長の長さを指定します。

//表示関連
$GAME_CONFIG['TITLE_TEMPLATE'] = ' | Teiki Adventure'; // ページタイトルに共通でつける文言を指定します。

//表示要素数関連
$GAME_CONFIG['CHARACTER_LIST_ITEMS_PER_PAGE'] = 100; // キャラクターリストページで1ページあたりに表示するキャラクター数を指定します。
$GAME_CONFIG['ROOM_LIST_ITEMS_PER_PAGE']      = 30;  // トークルーム一覧ページで1ページあたりに表示するトークルーム数を指定します。
$GAME_CONFIG['ROOM_MESSAGES_PER_PAGE']        = 20;  // トークルームで1ページあたりに表示するメッセージ数を指定します。
$GAME_CONFIG['THREADS_PER_PAGE']              = 50;  // 掲示板で1ページあたりに表示するスレッド数を指定します。
$GAME_CONFIG['ANNOUNCEMENTS_LIMIT']           = 5;   // お知らせ画面を表示した際のデフォルトのお知らせの表示件数を指定します。
$GAME_CONFIG['NOTIFICATIONS_LIMIT']           = 50;  // 通知画面を表示した際の通知の表示件数を指定します。

//通知関連
$GAME_CONFIG['DISCORD_NOTIFICATION_PREFIX'] = 'Teiki Adventure / '; // Discord通知の先頭に共通で付ける文言を指定します。
$GAME_CONFIG['DISCORD_WEBHOOK_PREFIX']      = 'https://discord.com/api/webhooks/'; // DiscordのウェブフックURLの共通先頭部分の文字列を指定します。

//タイムゾーン関連
$GAME_CONFIG['DEFAULT_TIMEZONE'] = 'Asia/Tokyo'; // date関数などで使用されるタイムゾーンを指定します。

//管理者＆公共トークルーム関連
$GAME_CONFIG['INITIAL_ADMINISTRATORS'] = array( // 初期化を行った際に作られる管理者アカウントを指定します。ENoは通常作成されない範囲で重複のないように指定してください（0以下を推奨）。
  array('ENo' =>  0, 'name' => '管理者A', 'nickname'=> '管理者A', 'password' => 'admin_password_a'),
  array('ENo' => -1, 'name' => '管理者B', 'nickname'=> '管理者B', 'password' => 'admin_password_b'),
  array('ENo' => -2, 'name' => '管理者C', 'nickname'=> '管理者C', 'password' => 'admin_password_c')
);

$GAME_CONFIG['PUBLIC_ROOMS'] = array( // 初期化を行った際に作られる公共トークルームを指定します。RNoは通常作成されない範囲で重複のないように指定してください（0以下を推奨）。内部的なRNo秘匿のためaliasにURI上の名称を指定してください。公共トークルームはRNoでのアクセスが不能で、aliasに指定した名称でアクセスします。また、一番最初に指定したものはroom.phpでroomのURLパラメータを省略した場合のアクセス先になります。
  array('RNo' => 0, 'alias'=> 'public', 'title' => '全体トークルーム', 'summary'=> '', 'description' => '')
);

//その他
$GAME_CONFIG['TOP_URI']    = $GAME_CONFIG['URI'].'';       // トップページのURIを指定します。ログアウト、登録解除した際のリダイレクト先及びログアウト状態でタイトルをクリックした際の遷移先になります。
$GAME_CONFIG['HOME_URI']   = $GAME_CONFIG['URI'].'home';   // ホームのURIを指定します。ログイン、新規登録した際のリダイレクト先及びログイン状態でタイトルをクリックした際の遷移先になります。
$GAME_CONFIG['SIGNIN_URI'] = $GAME_CONFIG['URI'].'signin'; // ログインページのURIを指定します。ログインが必要なページにログインしていない状態でアクセスした際のリダイレクト先になります。
?>