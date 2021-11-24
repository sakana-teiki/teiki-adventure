<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // ゲームステータスを取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `update_status`,
      `next_update_nth`
    FROM
      `game_status`;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result     = $statement->execute();
  $gameStatus = $statement->fetch();

  if (!$result || !$gameStatus) {
    responseError(500); // 結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  // POSTの場合
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('diary',   ['disallow-special-chars']) ||
      !validatePOST('members', ['non-empty'])
    ) {
      responseError(400);
    }

    // 探索を行うメンバーのENoを検証
    $memberENos       = [];
    $postedMemberENos = json_decode($_POST['members']);

    // 送信されたメンバー群の型が配列でなければ400(Bad Request)を返して処理を中断
    if (!is_array($postedMemberENos)) {
      responseError(400);
    }

    foreach ($postedMemberENos as $postedMemberENo) {
      if (!is_int($postedMemberENo) || $postedMemberENo <= 0) {
        // 送信されたメンバー群の値に整数でないものがある、あるいは0以下の値があれば400(Bad Request)を返して処理を中断
        responseError(400);
      } else {
        // そうでなければメンバーに追加
        $memberENos[] = $postedMemberENo;
      }
    }

    // パーティの最大人数を超えていれば400(Bad Request)を返して処理を中断
    if ($GAME_CONFIG['PARTY_MEMBERS_MAX'] < count($memberENos) + 1) {
      responseError(400);
    }

    // 連れ出そうとしているキャラクターにお気に入りしていないキャラクターがいないか検証

    // お気に入りしているキャラクターのENo群を取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `characters_favs`.`faved`
      FROM
        `characters_favs`
      JOIN
        `characters` ON `characters`.`ENo` = `characters_favs`.`faved`
      WHERE
        `characters_favs`.`faver` = :ENo AND `characters`.`deleted` = false;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    }

    $favCharacters = $statement->fetchAll();

    foreach ($memberENos as $memberENo) {
      $found = false;

      foreach ($favCharacters as $favCharacter) {
        if ($favCharacter['faved'] == $memberENo) {
          $found = true;
          break;
        }
      }

      if (!$found) {
        responseError(403); // 連れ出そうとしているキャラクターにお気に入りしていないキャラクターがいた場合は403(Forbidden)を返して処理を中断
      }
    }

    // メンバーの重複検証
    // 重複を取り除いた結果、メンバーの数が変わっていれば400(Bad Request)を返して処理を中断
    if (count($memberENos) !== count(array_unique($memberENos))) {
      responseError(400);
    }

    if (!$gameStatus['update_status']) {
      responseError(403); // 更新未確定状態で行動宣言しようとしていた場合403(Forbidden)を返して処理を中断
    }

    // トランザクション開始
    $GAME_PDO->beginTransaction();
    
    // 宣言状況の作成あるいは反映
    // nameカラムは更新処理時に確定させるためここでは空文字列
    $statement = $GAME_PDO->prepare("
      INSERT INTO `characters_declarations` (
        `ENo`,
        `nth`,
        `name`,
        `diary`
      ) VALUES (
        :ENo,
        :nth,
        '',
        :diary
      )

      ON DUPLICATE KEY UPDATE
        `diary` = :diary;
    ");

    $statement->bindParam(':ENo',   $_SESSION['ENo']);
    $statement->bindParam(':nth',   $gameStatus['next_update_nth']);
    $statement->bindParam(':diary', $_POST['diary']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // 失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // 既に登録されているメンバーを削除
    $statement = $GAME_PDO->prepare("
      DELETE FROM
        `characters_declarations_members`
      WHERE
        `ENo` = :ENo AND `nth` = :nth;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);
    $statement->bindParam(':nth', $gameStatus['next_update_nth']);

    $result = $statement->execute();

    if (!$result) {
      $GAME_PDO->rollBack();
      responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    // メンバーを登録
    foreach ($memberENos as $memberENo) {
      $statement = $GAME_PDO->prepare("
        INSERT INTO `characters_declarations_members` (
          `ENo`,
          `nth`,
          `member`
        ) VALUES (
          :ENo,
          :nth,
          :member
        );
      ");

      $statement->bindParam(':ENo',    $_SESSION['ENo']);
      $statement->bindParam(':nth',    $gameStatus['next_update_nth']);
      $statement->bindParam(':member', $memberENo);

      $result = $statement->execute();

      if (!$result) {
        $GAME_PDO->rollBack();
        responseError(500); // DBへの登録に失敗した場合は500(Internal Server Error)を返して処理を中断
      }
    }

    // ここまで全て成功した場合はコミット
    $GAME_PDO->commit();
  }

  // 宣言内容を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `diary`
    FROM
      `characters_declarations`
    WHERE
      `ENo` = :ENo AND
      `nth` = :nth;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);
  $statement->bindParam(':nth', $gameStatus['next_update_nth']);

  $result      = $statement->execute();
  $declaration = $statement->fetch();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  if (!$declaration) { // 未宣言の場合適切なデフォルト値を設定
    $declaration = array(
      'diary' => ''
    );
  }

  // 連れ出し可能なキャラクター（＝お気に入りしているキャラクター）を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `characters`.`ENo`,
      `characters`.`nickname`,
      `characters`.`ATK`,
      `characters`.`DEX`,
      `characters`.`MND`,
      `characters`.`AGI`,
      `characters`.`DEF`,
      `characters`.`summary`,
      (SELECT `url` FROM `characters_icons` WHERE `characters_icons`.`ENo` = `characters`.`ENo` LIMIT 1) AS `icon`,
      IFNULL((SELECT true FROM `characters_declarations_members` WHERE `ENo` = :ENo AND `nth` = :nth AND `member` = `characters`.`ENo`), false) AS `selected`
    FROM
      `characters_favs`
    JOIN
      `characters` ON `characters`.`ENo` = `characters_favs`.`faved`
    WHERE
      `characters_favs`.`faver` = :ENo AND `characters`.`deleted` = false;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);
  $statement->bindParam(':nth', $gameStatus['next_update_nth']);

  $result = $statement->execute();

  if (!$result) {
    responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
  }

  $selectableCharacters = $statement->fetchAll();

  $PAGE_SETTING['TITLE'] = '行動宣言';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.selectable-character {
  display: flex;
  padding: 10px;
  border: 1px solid lightgray;
  margin: 10px;
  border-radius: 4px;
  cursor: pointer;
  user-select: none;
}

.icon {
  width: 60px;
}

.description {
  flex-grow: 1;
  display: block;
  margin-left: 10px;
}

.name {
  font-size: 16px;
  font-weight: bold;
  color: #222222;
}

.eno {
  font-size: 13px;
  margin-left: 3px;
  color: gray;
}

.statuses {
  margin: 0;
}

.statuses th, .statuses td {
  padding: 0 8px 0 0;
  border: none;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', 'メイリオ', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
}

.status-name {
  font-weight: bold;
  color: #555;
  margin-right: 20px;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>行動宣言</h1>

<?php if (!$gameStatus['update_status']) { ?>
    <p>
      更新が未確定のため行動宣言を行えません。更新確定を今しばらくお待ち下さい。
    </p>  
<?php } else { ?>
<form id="form" method="post">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">
  <input type="hidden" id="members" name="members">

  <section>
    <h2>行動内容</h2>
    <section class="form">
      <div class="form-title">日記（<?=$GAME_CONFIG['DIARY_MAX_LENGTH']?>文字まで）</div>
      <div class="form-description">
        日記はプロフィールと同様の書式で装飾することができます。<br>
        詳しくはルールブックを確認してください。
      </div>
      <textarea id="input-diary" name="diary" class="form-textarea" placeholder="日記"><?=htmlspecialchars($declaration['diary'])?></textarea>
    </section>
  </section>

  <section>
    <h2>パーティメンバー選択</h2>
    <div class="form-description">
      連れ出すパーティメンバーを選択します。<br>
      お気に入りしているキャラクターの中から選択することができます。<br>
      パーティーメンバーはあなたを含めて最大<?=$GAME_CONFIG['PARTY_MEMBERS_MAX']?>人までです。
    </div>
    <div class="form-title">
      パーティメンバー（クリックで選択キャンセル）
    </div>

    <div id="selected-characters-area">
<?php foreach ($selectableCharacters as $selectableCharacter) { ?>
<?php if (!$selectableCharacter['selected']) continue;?>
      <section class="selectable-character" data-eno="<?=$selectableCharacter['ENo']?>">
        <div class="icon">
          <?php
            $COMPONENT_ICON['src'] = $selectableCharacter['icon'];
            include GETENV('GAME_ROOT').'/components/icon.php';
          ?>
        </div>
        <div class="description">
          <div>
            <span class="name"><?=htmlspecialchars($selectableCharacter['nickname'])?></span>
            <span class="eno">&lt; ENo.<?=$selectableCharacter['ENo']?> &gt;</span>
          </div>
          <table class="statuses">
            <tbody>
              <tr>
                <td class="status-name">ATK</td>
                <td class="status-value"><?=$selectableCharacter['ATK']?></td>
                <td class="status-name">DEX</td>
                <td class="status-value"><?=$selectableCharacter['DEX']?></td>
                <td class="status-name">MND</td>
                <td class="status-value"><?=$selectableCharacter['MND']?></td>
                <td class="status-name">AGI</td>
                <td class="status-value"><?=$selectableCharacter['AGI']?></td>
                <td class="status-name">DEF</td>
                <td class="status-value"><?=$selectableCharacter['DEF']?></td>
              </tr>
            </tbody>
          </table>
          <div class="summary">
            <?=htmlspecialchars($selectableCharacter['summary'])?>
          </div>
        </div>
      </section>
<?php } ?>
    </div>

    <br>

    <div class="form-title">
      連れ出せるキャラクター（クリックで選択）
    </div>

<?php if (!$selectableCharacters) { ?>
    <div class="form-description">
      お気に入りしているキャラクターがいません。<br>
      お気に入りはキャラクター一覧からキャラクターページにアクセスし<br>
      「お気に入りする」ボタンをクリックすることで行なえます。
    </div>
<?php } else { ?>
    <section id="selectable-characters-area">
<?php foreach ($selectableCharacters as $selectableCharacter) { ?>
<?php if ($selectableCharacter['selected']) continue;?>
      <section class="selectable-character" data-eno="<?=$selectableCharacter['ENo']?>">
        <div class="icon">
          <?php
            $COMPONENT_ICON['src'] = $selectableCharacter['icon'];
            include GETENV('GAME_ROOT').'/components/icon.php';
          ?>
        </div>
        <div class="description">
          <div>
            <span class="name"><?=htmlspecialchars($selectableCharacter['nickname'])?></span>
            <span class="eno">&lt; ENo.<?=$selectableCharacter['ENo']?> &gt;</span>
          </div>
          <table class="statuses">
            <tbody>
              <tr>
                <td class="status-name">ATK</td>
                <td class="status-value"><?=$selectableCharacter['ATK']?></td>
                <td class="status-name">DEX</td>
                <td class="status-value"><?=$selectableCharacter['DEX']?></td>
                <td class="status-name">MND</td>
                <td class="status-value"><?=$selectableCharacter['MND']?></td>
                <td class="status-name">AGI</td>
                <td class="status-value"><?=$selectableCharacter['AGI']?></td>
                <td class="status-name">DEF</td>
                <td class="status-value"><?=$selectableCharacter['DEF']?></td>
              </tr>
            </tbody>
          </table>
          <div class="summary">
            <?=htmlspecialchars($selectableCharacter['summary'])?>
          </div>
        </div>
      </section>
<?php } ?>
    </section>
<?php } ?>
  </section>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button class="button" type="submit">反映</button>
  </div>
</form>
<?php } ?>

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

  // 選択されているキャラクターの取得
  function getSelectedCharacterENos() {
    var selectedENos = [];

    $('#selected-characters-area > .selectable-character').each(function(i, element){
      selectedENos.push($(element).data().eno);
    });

    return selectedENos;
  }

  // キャラクターの選択
  $('#selectable-characters-area').on('click', '.selectable-character', function() {
    if (<?=$GAME_CONFIG['PARTY_MEMBERS_MAX']?> - 1 <= getSelectedCharacterENos().length) {
      alert("すでにパーティメンバーは埋まっています");
    } else {
      $('#selected-characters-area').append(this);
    }
  });

  // キャラクターの選択解除
  $('#selected-characters-area').on('click', '.selectable-character', function() {
    $('#selectable-characters-area').append(this);

    // ENo順に並び替え
    $('#selectable-characters-area').html(
      $('#selectable-characters-area > .selectable-character').sort(function(a, b) {
        return $(a).data().eno - $(b).data().eno;
      })
    );
  });

  $('#form').submit(function(){
    // 各種の値を取得
    var inputDiary = $('#input-diary').val();

    // 入力値検証
    // 日記が長すぎる場合エラーメッセージを表示して送信を中断
    if (inputDiary.length > <?=$GAME_CONFIG['DIARY_MAX_LENGTH']?>) {
      showErrorMessage('日記が長すぎます');
      return false;
    }

    // レスポンス待ちの場合アラートを表示して送信を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // 送信
    $('#members').val(JSON.stringify(getSelectedCharacterENos())); // 連れ出しキャラクターをJSON化されたENoの配列として#membersに設定

    // 上記のどれにも当てはまらない場合送信が行われるためレスポンス待ちをONに
    waitingResponse = true;
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>