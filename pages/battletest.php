<!DOCTYPE html>
  <html lang="ja">
  <head>
    <meta charset="UTF-8">
    <title><%- title %></title>
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
    </style>
  </head>
<body>
  <section class="stage">
    <div class="title"><%- title %></div>
    <section class="description"><%- description %></section>
  </section>

<?php

  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  $memberENos = [1];








  $stageId = 3;



  
?>

</body>
</html>