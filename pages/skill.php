<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('atk', ['non-empty', 'non-negative-integer']) ||
      !validatePOST('dex', ['non-empty', 'non-negative-integer']) ||
      !validatePOST('mnd', ['non-empty', 'non-negative-integer']) ||
      !validatePOST('agi', ['non-empty', 'non-negative-integer']) ||
      !validatePOST('def', ['non-empty', 'non-negative-integer'])
    ) {
      responseError(400);
    }

    // 合計量の計算
    $sum =
      intval($_POST['atk']) +
      intval($_POST['dex']) +
      intval($_POST['mnd']) +
      intval($_POST['agi']) +
      intval($_POST['def']);

    // ステータスの反映
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `NP`  = `NP`  - :sum,
        `ATK` = `ATK` + :atk,
        `DEX` = `DEX` + :dex,
        `MND` = `MND` + :mnd,
        `AGI` = `AGI` + :agi,
        `DEF` = `DEF` + :def
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':sum', $sum         , PDO::PARAM_INT);
    $statement->bindParam(':atk', $_POST['atk'], PDO::PARAM_INT);
    $statement->bindParam(':dex', $_POST['dex'], PDO::PARAM_INT);
    $statement->bindParam(':mnd', $_POST['mnd'], PDO::PARAM_INT);
    $statement->bindParam(':agi', $_POST['agi'], PDO::PARAM_INT);
    $statement->bindParam(':def', $_POST['def'], PDO::PARAM_INT);
    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // 更新に失敗した場合は500(Internal Server Error)を返して処理を中断
    }
  }

  // DBから値を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `ENo`,
      `NP`,
      `ATK`,
      `DEX`,
      `MND`,
      `AGI`,
      `DEF`
    FROM
      `characters`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result    = $statement->execute();
  $character = $statement->fetch();

  if (!$result || !$character) {
    // SQLの実行や実行結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  $PAGE_SETTING['TITLE'] = '戦闘設定';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.remaining-np {
  padding: 20px;
  font-weight: bold;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  font-size: 24px;
  color: #666;
}

.remaining-np-error {
  color: #c53f4d;
}

.statuses {
  border-collapse: collapse;
  padding-left: 20px;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
  margin-bottom: 20px;
}

.statuses th, .statuses td {
  padding-top: 10px;
  padding-bottom: 10px;
  border-width: 1px 0px;
  border-color: lightgray;
  border-style: solid;
}

.statuses-key th {
  text-align: left;
  padding-left: 20px;
}

.statuses-value td {
  padding-left: 20px;
}

.statuses-input td {
  padding-left: 11px;
}

.statuses-input input {
  margin: 0;
  width: 100px;
}

.statuses-sum td {
  padding-left: 20px;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>戦闘設定</h1>

<section>
  <h2>能力値割り振り</h2>

  <form id="form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

    <section class="form">
      <div class="form-description">
        所持しているNPを能力値に割り振ることができます。<br>
        入力欄に各能力値に割り振りたい値を入力してください。
      </div>
      <div class="remaining-np">
        残りNP:
        <span id="remaining-np-count"><?= $character['NP'] ?></span>
      </div>
      <table class="statuses">
        <tbody>
          <tr class="statuses-key">
            <th>ATK</th>
            <th>DEX</th>
            <th>MND</th>
            <th>AGI</th>
            <th>DEF</th>
          </tr>
          <tr class="statuses-value">
            <td><?=$character['ATK']?></td>
            <td><?=$character['DEX']?></td>
            <td><?=$character['MND']?></td>
            <td><?=$character['AGI']?></td>
            <td><?=$character['DEF']?></td>
          </tr>
          <tr class="statuses-input">
            <td><input id="input-atk" name="atk" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
            <td><input id="input-dex" name="dex" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
            <td><input id="input-mnd" name="mnd" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
            <td><input id="input-agi" name="agi" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
            <td><input id="input-def" name="def" type="number" value="0" min="0" max="<?=$character['NP']?>"></td>
          </tr>
          <tr class="statuses-sum">
            <td id="sum-atk">0</td>
            <td id="sum-dex">0</td>
            <td id="sum-mnd">0</td>
            <td id="sum-agi">0</td>
            <td id="sum-def">0</td>
          </tr>
        </tbody>
      </table>

      <div id="error-message-area"></div>

      <div class="button-wrapper">
        <button class="button" type="submit">更新</button>
      </div>
    </form>
  </section>
</section>

<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）

  // エラーメッセージを表示する関数及びその関連処理
  var errorMessageArea = $('#error-message-area');
  function showErrorMessage(message) {
    errorMessageArea.empty();
    errorMessageArea.append(
      '<div class="message-banner message-banner-error">'+
        message +
      '</div>'
    );
  }

  // 現在のステータス、NP
  var currentNP = <?= $character['NP'] ?>;
  var currentStatuses = {
    atk: <?= $character['ATK'] ?>,
    dex: <?= $character['DEX'] ?>,
    mnd: <?= $character['MND'] ?>,
    agi: <?= $character['AGI'] ?>,
    def: <?= $character['DEF'] ?>
  };
  
  // ステータス入力欄
  var inputATK = $('#input-atk');
  var inputDEX = $('#input-dex');
  var inputMND = $('#input-mnd');
  var inputAGI = $('#input-agi');
  var inputDEF = $('#input-def');

  // 残りNP欄
  var remainingAPCount = $('#remaining-np-count');

  // 0以上の整数かどうかを判定するパターン（0埋め不許可）
  var checkPattern = 	/^([1-9][0-9]*|0)$/;

  // 合計量を計算する関数
  // エラーがあれば-1を返す
  function sumInputStatuses() {
    var inputATKValue = inputATK.val();
    var inputDEXValue = inputDEX.val();
    var inputMNDValue = inputMND.val();
    var inputAGIValue = inputAGI.val();
    var inputDEFValue = inputDEF.val();

    // どれかが入力のパターンチェックを通らなければ-1を返す
    if (
      !checkPattern.test(inputATKValue) ||
      !checkPattern.test(inputDEXValue) ||
      !checkPattern.test(inputMNDValue) ||
      !checkPattern.test(inputAGIValue) ||
      !checkPattern.test(inputDEFValue)
    ) {
      return -1;
    }

    // OKなら合計値を返す
    return (
      Number(inputATKValue) +
      Number(inputDEXValue) +
      Number(inputMNDValue) +
      Number(inputAGIValue) +
      Number(inputDEFValue)
    );
  }
  
  // 入力欄に入力イベントがあった際の表示更新処理を設定するための関数
  function setReactiveEventsOnInputStatus(statusNameLower) {
    $('#input-'+statusNameLower).on('change', function() {
      var status = statusNameLower;
      var input = $('#input-'+status).val();

      // 該当の合計欄を更新
      // 入力内容がパターンチェックを通らなければERRと表示
      if (checkPattern.test(input)) {
        $('#sum-'+status).text(currentStatuses[status] + Number(input));
      } else {
        $('#sum-'+status).text('ERR');
      }
      
      // 合計量を取得
      var sum = sumInputStatuses();

      if (sum == -1 || currentNP - sum < 0) {
        // エラーもしくは合計がNPを超えている場合はエラー表示
        remainingAPCount.addClass('remaining-np-error');

        if (sum == -1) {
          // テキストをエラー表示に
          remainingAPCount.text('エラー');
        } else {
          // テキストを超過NP量表示に
          remainingAPCount.text(currentNP - sum);
        }
      } else {
        // そうでなければエラー表示を外し残りNP量を表示
        remainingAPCount.removeClass('remaining-np-error');
        remainingAPCount.text(currentNP - sum);
      }
    });
  }

  // 各ステータスを入力時に表示更新処理をするように設定
  setReactiveEventsOnInputStatus('atk');
  setReactiveEventsOnInputStatus('dex');
  setReactiveEventsOnInputStatus('mnd');
  setReactiveEventsOnInputStatus('agi');
  setReactiveEventsOnInputStatus('def');

  $('#form').submit(function(){
    var sum = sumInputStatuses(); // 入力の合計値を取得

    // 入力値検証
    // ステータス割り振りの入力欄が不正な場合はエラーメッセージを表示して送信を中断
    if (sum == -1) {
      showErrorMessage('ステータス割り振りの入力欄が不正です');
      return false;
    }

    // ステータスの割り振り量が残りNPを超過している場合はエラーメッセージを表示して送信を中断
    if (currentNP - sum < 0) {
      showErrorMessage('ステータスの割り振り量が残りNPを超過しています');
      return false;
    }

    // レスポンス待ちの場合アラートを表示して送信を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 上記のどれにも当てはまらない場合送信が行われるためレスポンス待ちをONに
    waitingResponse = true;
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>