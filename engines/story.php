<?php 
  /*
    更新結果を出力します。
    以下のデータを受け取ります。

    $ENGINES_STORY['diary'] string
    行動宣言の日記の内容です。
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
  </style>
  </head>
  <body>
    <section class="diary">
      <div class="diary-title">日記</div>
      <section class="diary-body"><?=profileDecoration($ENGINES_STORY['diary'])?></section>
    </section>
  </body>
</html>