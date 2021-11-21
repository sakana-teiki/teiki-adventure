<?php 
  /*
    更新結果を出力します。
    以下のデータを受け取ります。

    $ENGINES_STORY['diary'] string
    行動宣言の日記の内容です。


    $ENGINES_STORY['stage'] array
    探索するステージ情報の連想配列です。その構造は以下のとおりです。

      $ENGINES_STORY['stage']['title'] string
      ステージのタイトルです。

      $ENGINES_STORY['stage']['pre_text'] string
      ステージの戦闘前テキストです。

      $ENGINES_STORY['stage']['post_text'] string
      ステージの戦闘後テキストです。

    $ENGINES_STORY['log'] string
    戦闘の実行ログです。
  */

  require_once GETENV('GAME_ROOT').'/utils/decoration.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>更新結果</title>
  <style>
    html {
      overflow-y: scroll;
    }

    body {
      background: #F8F8F8;
      color: #333;
      font-size: 16px;
      font-family: 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
    }

    .diary {
      margin: 0 auto;
      width: 1000px;
    }

    .diary-title {
      box-sizing: border-box;
      padding: 20px 20px;
      margin-bottom: 20px;
      width: 100%;
      font-size: 36px;
      letter-spacing: 2px;
      border-bottom: 1px solid lightgray;
      color: #444;
      font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
    }

    .diary-body {
      padding: 20px;
    }

    .stage {
      margin: 0 auto;
      width: 1000px;
    }

    .title {
      box-sizing: border-box;
      padding: 20px 20px;
      margin-bottom: 20px;
      width: 100%;
      font-size: 36px;
      letter-spacing: 2px;
      border-bottom: 1px solid lightgray;
      color: #444;
      font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
    }

    .description {
      padding: 20px;
    }

    .battle {
      margin: 0 auto;
      width: 1000px;
    }

    .battle-start {
      width: 100%;
    }

    .battle-start-call {
      box-sizing: border-box;
      padding: 20px 20px;
      margin-bottom: 20px;
      width: 100%;
      font-size: 36px;
      letter-spacing: 2px;
      border-bottom: 1px solid lightgray;
      color: #444;
      font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
    }

    .battle-start-call::first-letter {
      font-size: 44px;
    }

    .round {
      padding: 10px;
    }

    .round-start {
      box-sizing: border-box;
      padding: 20px 20px;
      width: 100%;
      border-bottom: 1px solid lightgray;
      font-size: 24px;
      color: #666;
      font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
    }

    .round-count {
      font-size: 48px;
      color: #444;
    }

    .skill {
      margin-left: 10px;
    }

    .message {
      font-size: 12px;
    }

    .statuses {
      display: flex;
      justify-content: space-around;
      margin: 20px 0;
    }

    .team {
      width: 45%;
    }

    .unit {
      margin: 16px 0;
    }

    .unit-name {
      font-weight: bold;
      font-size: 24px;
      color: #666;
    }

    .statusbars {
      margin: 0 10px;
      display: flex;
    }

    .statusbar {
      width: 150px;
      margin: 0 10px;
    }

    .statusbar-desc {
      margin: 0 10px;
      display: flex;
      justify-content: space-between;
    }

    .gauge-wrapper {
      position: relative;
      width: 100%;
      height: 10px;
      background: #DDD;
      transform: skewX(-30deg);
    }

    .gauge {
      position: absolute;
      height: 10px;
      background: #777;
    }

    .turns {
      margin: 20px 0px 0px 20px;
    }

    .turn {
      padding: 10px;
    }

    .actor {
      font-weight: bold;
      font-size: 20px;
      color: #444;
    }

    .action {
      margin: 10px 0 10px 16px;
    }
      
    .action-twice {
      font-weight: bold;
      font-size: 16px;
      color: #444;
    }

    .skill-name {
      font-weight: bold;
      font-size: 22px;
      color: #444;
    }

    .damage {
      font-weight: bold;
    }
      
    .damage-gte50 {
      font-size: 150%;
    }
      
    .damage-gte100 {
      font-size: 200%;
    }
      
    .heal {
      font-weight: bold;
    }
      
    .clean-up {
      margin-left: 30px;
    }

    .battle-result {
      box-sizing: border-box;
      width: 100%;
      padding: 20px;
      border-top: 1px solid lightgray;
      font-weight: bold;
      font-size: 30px;
    }

    .text {
      padding: 20px;
    }
    
    .log {
      margin: 0 auto;
      width: 1000px;
    }

    .log-start-text {
      box-sizing: border-box;
      padding: 20px 20px;
      margin-bottom: 20px;
      width: 100%;
      font-size: 36px;
      letter-spacing: 2px;
      border-bottom: 1px solid lightgray;
      color: #444;
      font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
    }

    .log-body {
      padding: 20px;
    }
  </style>
  </head>
  <body>
    <section class="diary">
      <div class="diary-title">日記</div>
      <section class="diary-body"><?=profileDecoration($ENGINES_STORY['diary'])?></section>
    </section>
    <section class="stage">
      <div class="title"><?=$ENGINES_STORY['stage']['title']?></div>
      <section class="text"><?=$ENGINES_STORY['stage']['pre_text']?></section>
    </section>
    <?=$ENGINES_STORY['log']?>
    <section class="stage">
      <div class="title">戦闘終了</div>
      <section class="text"><?=$ENGINES_STORY['stage']['post_text']?></section>
    </section>
  </body>
</html>