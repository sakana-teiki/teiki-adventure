<?php 
  /*
    探索ログを出力します。
    以下のデータを受け取ります。

    $ENGINES_EXPLORATION['stage'] array
    探索するステージ情報の連想配列です。その構造は以下のとおりです。

      $ENGINES_EXPLORATION['stage']['title'] string
      ステージのタイトルです。

      $ENGINES_EXPLORATION['stage']['text']
      ステージのテキストです。

    $ENGINES_EXPLORATION['character'] array
    キャラクター情報の連想配列です。その構造は以下のとおりです。

      $ENGINES_EXPLORATION['character']['nickname'] string
      キャラクターの短縮名です。

      $ENGINES_EXPLORATION['character']['ATK'] int
      $ENGINES_EXPLORATION['character']['DEX'] int
      $ENGINES_EXPLORATION['character']['MND'] int
      $ENGINES_EXPLORATION['character']['AGI'] int
      $ENGINES_EXPLORATION['character']['DEF'] int
      キャラクターの各ステータスです。

    $ENGINES_EXPLORATION['drop_items'] array
    探索時にドロップするアイテムの配列です。配列の各値は連想配列であり、構造は以下のとおりです。

      $ENGINES_EXPLORATION['drop_items'][0]['item'] int
      ドロップするアイテムのアイテムIDです。

      $ENGINES_EXPLORATION['drop_items'][0]['name'] string
      ドロップするアイテムの名称です。
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
    <section class="stage">
      <div class="title"><?=$ENGINES_EXPLORATION['stage']['title']?></div>
      <section class="text"><?=$ENGINES_EXPLORATION['stage']['text']?></section>
    </section>
    <section class="log">
      <div class="log-start-text">探索結果</div>
      <section class="log-body">
        <?= htmlspecialchars($ENGINES_EXPLORATION['character']['nickname']) ?>は探索を行った！<br>
<?php foreach ($ENGINES_EXPLORATION['drop_items'] as $item) { ?>
        <?= $item['name'] ?>を得た！<br>
<?php } ?>
      </section>
    </section>
  </body>
</html>