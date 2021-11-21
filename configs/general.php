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

//ユーザー入力値関連
$GAME_CONFIG['CHARACTER_NAME_MAX_LENGTH']     = 16;   // 名前の最長の長さを指定します。
$GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH'] = 8;    // 愛称の最長の長さ及びトークルームでの名前の最長の長さを指定します。
$GAME_CONFIG['CHARACTER_SUMMARY_MAX_LENGTH']  = 40;   // キャラクターのサマリーの最長の長さを指定します。
$GAME_CONFIG['CHARACTER_PROFILE_MAX_LENGTH']  = 2000; // キャラクターのプロフィールの最長の長さを指定します。
$GAME_CONFIG['CHARACTER_TAG_MAX']             = 16;   // キャラクターのタグの数の最大を指定します。
$GAME_CONFIG['CHARACTER_TAG_MAX_LENGTH']      = 16;   // キャラクターのタグの最大の長さを指定します。
$GAME_CONFIG['CHARACTER_ICON_MAX']            = 30;   // キャラクターの設定できるアイコンの最大数を指定します。
$GAME_CONFIG['DIARY_MAX_LENGTH']              = 4000; // 日記の最長の長さを指定します。
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
$GAME_CONFIG['EXPLORATION_LOGS_PER_PAGE']     = 20;  // 探索ログで1ページあたりに表示するログ数を指定します。
$GAME_CONFIG['THREADS_PER_PAGE']              = 50;  // 掲示板で1ページあたりに表示するスレッド数を指定します。
$GAME_CONFIG['DIRECT_MESSAGES_PER_PAGE']      = 50;  // 個別のダイレクトメッセージで1ページあたりに表示するダイレクトメッセージ数を指定します。
$GAME_CONFIG['ANNOUNCEMENTS_LIMIT']           = 5;   // お知らせ画面を表示した際のデフォルトのお知らせの表示件数を指定します。
$GAME_CONFIG['NOTIFICATIONS_LIMIT']           = 50;  // 通知画面を表示した際の通知の表示件数を指定します。
$GAME_CONFIG['TRADE_HISTORIES_LIMIT']         = 50;  // トレード履歴の表示件数を指定します。
$GAME_CONFIG['FLEA_MARKET_ITEMS_PER_PAGE']    = 30;  // フリーマーケットページで1ページあたりに表示する出品数を指定します。

//通知関連
$GAME_CONFIG['DISCORD_NOTIFICATION_PREFIX'] = 'Teiki Adventure / '; // Discord通知の先頭に共通で付ける文言を指定します。
$GAME_CONFIG['WEBHOOK_ACCEPTABLE_PREFIXES'] = [                     // ウェブフックURLの共通先頭部分の文字列を指定します。複数指定可能です。一番上のURLのものが設定ページでプレースホルダ表示されます。
  'https://discordapp.com/api/webhooks/',
  'https://discord.com/api/webhooks/'
]; 

//タイムゾーン関連
$GAME_CONFIG['DEFAULT_TIMEZONE'] = 'Asia/Tokyo'; // date関数などで使用されるタイムゾーンを指定します。

//管理者＆公共トークルーム関連
$GAME_CONFIG['INITIAL_ADMINISTRATORS'] = array( // 初期化を行った際に作られる管理者アカウントを指定します。ENoは通常作成されない範囲で重複のないように指定してください（0以下を推奨）。
  array('ENo' =>  0, 'name' => '管理者A', 'nickname'=> '管理者A', 'password' => 'admin_password_a'),
  array('ENo' => -1, 'name' => '管理者B', 'nickname'=> '管理者B', 'password' => 'admin_password_b'),
  array('ENo' => -2, 'name' => '管理者C', 'nickname'=> '管理者C', 'password' => 'admin_password_c')
);

$GAME_CONFIG['PUBLIC_ROOMS'] = array( // 初期化を行った際に作られる公式トークルームを指定します。RNoは通常作成されない範囲で重複のないように指定してください（0以下を推奨）。内部的なRNo秘匿のためaliasにURI上の名称を指定してください。公共トークルームはRNoでのアクセスが不能で、aliasに指定した名称でアクセスします。また、一番最初に指定したものはroom.phpでroomのURLパラメータを省略した場合のアクセス先になります。
  array('RNo' => 0, 'alias'=> 'public', 'title' => '全体トークルーム', 'summary'=> '', 'description' => '')
);

//マスタデータ関連
$GAME_CONFIG['MASTER_DATA_TABLES'] = [ // マスタデータを指定しているテーブルです。これにより指定されたテーブルはマスタデータインポートエクスポートで入出力されるようになります。
  'skills_master_data',
  'enemies_master_data',
  'enemies_master_data_battle_lines',
  'enemies_master_data_skills',
  'items_master_data',
  'items_master_data_effects',
  'exploration_stages_master_data',
  'exploration_stages_master_data_enemies',
  'exploration_stages_master_data_drop_items',
  'story_stages_master_data',
  'story_stages_master_data_enemies'
];

$GAME_CONFIG['MASTER_DATA_TABLES_CONTAINS_FOREIGN_KEY'] = array( // マスタデータを指定しているテーブルのうち、マスタデータ外のテーブルにて外部キー制約により指定されるテーブル及び外部キー制約の対象となるキーです。これにより指定されたテーブルはマスタデータインポート時にforeign_keyを主キーとしてUPSERT形式でロードされるようになります。
  array('name' => 'skills_master_data',             'foreign_key' => 'skill_id'),
  array('name' => 'enemies_master_data',            'foreign_key' => 'enemy_id'),
  array('name' => 'items_master_data',              'foreign_key' => 'item_id'),
  array('name' => 'exploration_stages_master_data', 'foreign_key' => 'stage_id'),
  array('name' => 'story_stages_master_data',       'foreign_key' => 'stage_id')
);

// 戦闘設定
$GAME_CONFIG['PARTY_MEMBERS_MAX'] = 5; // パーティーメンバーの最大人数を指定します。

//その他
$GAME_CONFIG['TOP_URI']    = $GAME_CONFIG['URI'].'';       // トップページのURIを指定します。ログアウト、登録解除した際のリダイレクト先及びログアウト状態でタイトルをクリックした際の遷移先になります。
$GAME_CONFIG['HOME_URI']   = $GAME_CONFIG['URI'].'home';   // ホームのURIを指定します。ログイン、新規登録した際のリダイレクト先及びログイン状態でタイトルをクリックした際の遷移先になります。
$GAME_CONFIG['SIGNIN_URI'] = $GAME_CONFIG['URI'].'signin'; // ログインページのURIを指定します。ログインが必要なページにログインしていない状態でアクセスした際のリダイレクト先になります。
?>