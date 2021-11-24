<?php 
  /*
    探索ログを出力します。
    以下のデータを受け取ります。

    $ENGINES_EXPLORATION['stage'] array
    探索するステージ情報の連想配列です。その構造は以下のとおりです。

      $ENGINES_EXPLORATION['stage']['title'] string
      ステージのタイトルです。

      $ENGINES_EXPLORATION['stage']['text'] string
      ステージのテキストです。

    $ENGINES_EXPLORATION['drop_items'] array
    勝利した際にドロップするアイテムの配列です。配列の各値は連想配列であり、構造は以下のとおりです。

      $ENGINES_EXPLORATION['drop_items'][0]['item'] int
      ドロップするアイテムのアイテムIDです。

      $ENGINES_EXPLORATION['drop_items'][0]['name'] string
      ドロップするアイテムの名称です。

    $ENGINES_EXPLORATION['log'] string
    戦闘の実行ログです。

    $ENGINES_EXPLORATION['result'] string
    戦闘の結果です。勝利であれば'win'、敗北であれば'lose'、引き分けであれば'even'です。

  */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?=$ENGINES_EXPLORATION['stage']['title'].$GAME_CONFIG['TITLE_TEMPLATE']?></title>
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

    .dialog {
      display: flex;
      border: 1px solid lightgray;
      padding: 4px;
      border-radius: 4px;
      margin: 8px 8px 8px 0;
      width: 400px;
    }

    .dialog-icon-area {
      margin: 4px;
      width: 60px;
      height: 60px;
    }

    .dialog-icon {
      width: 100%;
      height: 100%;
    }

    .dialog-body {
      margin: 4px;
    }

    .name {
      font-weight: bold;
      color: #666;
    }

    .dialog-message {
      margin-top: 4px;
      font-size: 14px;
    }
  </style>
  </head>
  <body>
    <section class="stage">
      <div class="title"><?=$ENGINES_EXPLORATION['stage']['title']?></div>
      <section class="text"><?=$ENGINES_EXPLORATION['stage']['text']?></section>
    </section>
    <?=$ENGINES_EXPLORATION['log']?>
<?php if ($ENGINES_EXPLORATION['result'] === 'win') { ?>
    <section class="log">
      <div class="log-start-text">探索結果</div>
      <section class="log-body">
<?php foreach ($ENGINES_EXPLORATION['drop_items'] as $item) { ?>
        <?= $item['name'] ?>を得た！<br>
<?php } ?>
      </section>
    </section>
<?php } ?>
  </body>
</html>