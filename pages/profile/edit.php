<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';
  require_once GETENV('GAME_ROOT').'/utils/parser.php';

  // アイコンの付与上限数を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `additional_icons`
    FROM
      `characters`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result          = $statement->execute();
  $additionalIcons = $statement->fetch();

  if (!$result || !$additionalIcons) {
    // SQLの実行や実行結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  // POSTの場合
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証1
    // 以下の条件のうちいずれかを満たせば400(Bad Request)を返し処理を中断
    if (
      !validatePOST('profile_images', ['disallow-special-chars']) ||
      !validatePOST('profile',        ['disallow-special-chars']) ||
      !validatePOST('tags',           ['single-line', 'disallow-special-chars']) ||
      !validatePOST('summary',        ['single-line', 'disallow-special-chars'], $GAME_CONFIG['CHARACTER_SUMMARY_MAX_LENGTH']) || 
      !validatePOST('name',           ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['CHARACTER_NAME_MAX_LENGTH'])     ||
      !validatePOST('nickname',       ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH'])
    ) {
      http_response_code(400);
      exit;
    }

    // 入力値検証2
    // 各アイコンについて以下の条件のうちいずれかを満たすものがあれば400(Bad Request)を返し処理を中断
    for ($i = 0; $i < $GAME_CONFIG['ICONS_MAX']; $i++) {
      if (
        !isset($_POST['icon-'.$i.'-name']) || // 受け取ったデータに名前がない
        !isset($_POST['icon-'.$i.'-url'])     // 受け取ったデータにURLがない
      ) {
        http_response_code(400);
        exit;
      }
    }

    // トランザクション開始
    $GAME_PDO->beginTransaction();

    // キャラクター情報のアップデート
    $statement = $GAME_PDO->prepare("
      UPDATE
        `characters`
      SET
        `name`     = :name,
        `nickname` = :nickname,
        `summary`  = :summary,
        `profile`  = :profile
      WHERE
        `ENo` = :ENo;
    ");
    
    $statement->bindParam(':ENo',      $_SESSION['ENo']);
    $statement->bindParam(':name',     $_POST['name']);
    $statement->bindParam(':nickname', $_POST['nickname']);
    $statement->bindParam(':summary',  $_POST['summary']);
    $statement->bindParam(':profile',  $_POST['profile']);

    $result = $statement->execute();

    if (!$result) { // 失敗した場合は500(Internal Server Error)を返してロールバックし、処理を中断
      http_response_code(500);
      $GAME_PDO->rollBack();
      exit;
    }

    // すでに登録されているプロフィール画像の削除
    $statement = $GAME_PDO->prepare("
      DELETE FROM
        `characters_profile_images`
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();
    
    if (!$result) {
      http_response_code(500);
      $GAME_PDO->rollBack();
      exit;
    }

    // すでに登録されているタグの削除
    $statement = $GAME_PDO->prepare("
      DELETE FROM
        `characters_tags`
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();
    
    if (!$result) {
      http_response_code(500);
      $GAME_PDO->rollBack();
      exit;
    }

    // すでに登録されているアイコンの削除
    $statement = $GAME_PDO->prepare("
      DELETE FROM
        `characters_icons`
      WHERE
        `ENo` = :ENo;
    ");

    $statement->bindParam(':ENo', $_SESSION['ENo']);

    $result = $statement->execute();
    
    if (!$result) {
      http_response_code(500);
      $GAME_PDO->rollBack();
      exit;
    }

    // プロフィール画像の登録
    $profile_images = explode("\n", $_POST['profile_images']);
    $profile_images = array_filter($profile_images, "strlen"); // 空の要素は削除する

    foreach ($profile_images as $profile_image) {
      $statement = $GAME_PDO->prepare("
        INSERT INTO `characters_profile_images` (
          `ENo`, `url`
        ) VALUES (
          :ENo, :url
        );
      ");

      $statement->bindParam(':ENo', $_SESSION['ENo']);
      $statement->bindParam(':url', $profile_image);

      $result = $statement->execute();
      
      if (!$result) {
        http_response_code(500);
        $GAME_PDO->rollBack();
        exit;
      }
    }

    // タグの登録
    $tags = explode(' ', $_POST['tags']);
    $tags = array_filter($tags, "strlen"); // 空の要素は削除する

    foreach ($tags as $tag) {
      $statement = $GAME_PDO->prepare("
        INSERT INTO `characters_tags` (
          `ENo`, `tag`
        ) VALUES (
          :ENo, :tag
        );
      ");

      $statement->bindParam(':ENo', $_SESSION['ENo']);
      $statement->bindParam(':tag', $tag);

      $result = $statement->execute();
      
      if (!$result) {
        http_response_code(500);
        $GAME_PDO->rollBack();
        exit;
      }
    }

    // アイコンの登録
    for ($i = 0; $i < $GAME_CONFIG['ICONS_MAX'] + $additionalIcons['additional_icons']; $i++) {
      // 名前もURLも登録されていない項目は処理を飛ばす
      if (strlen($_POST['icon-'.$i.'-name']) == 0 && strlen($_POST['icon-'.$i.'-url']) == 0) {
        continue;
      }

      $statement = $GAME_PDO->prepare("
        INSERT INTO `characters_icons` (
          `ENo`, `name`, `url`
        ) VALUES (
          :ENo, :name, :url
        );
      ");

      $statement->bindParam(':ENo',  $_SESSION['ENo']);
      $statement->bindParam(':name', $_POST['icon-'.$i.'-name']);
      $statement->bindParam(':url',  $_POST['icon-'.$i.'-url']);

      $result = $statement->execute();
      
      if (!$result) {
        http_response_code(500);
        $GAME_PDO->rollBack();
        exit;
      }
    }

    // ここまで全て成功した場合はコミット
    $GAME_PDO->commit();
  }

  // キャラクターの設定値を取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `ENo`,
      `name`,
      `nickname`,
      `summary`,
      `profile`,
      (SELECT GROUP_CONCAT(`url`               SEPARATOR '\n') FROM `characters_profile_images` WHERE `ENo` = :ENo GROUP BY `ENo`) AS `profile_images`,
      (SELECT GROUP_CONCAT(`tag`               SEPARATOR ' ')  FROM `characters_tags`           WHERE `ENo` = :ENo GROUP BY `ENo`) AS `tags`,
      (SELECT GROUP_CONCAT(`name`, '\n', `url` SEPARATOR '\n') FROM `characters_icons`          WHERE `ENo` = :ENo GROUP BY `ENo`) AS `icons`
    FROM
      `characters`
    WHERE
      `ENo` = :ENo;
  ");

  $statement->bindParam(':ENo', $_SESSION['ENo']);

  $result = $statement->execute();
  $data   = $statement->fetch();

  if (!$result || !$data) {
    // SQLの実行や実行結果の取得に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  $icons = parseIconsResult($data['icons']);

  $PAGE_SETTING['TITLE'] = 'プロフィール編集';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

#profile-edit-icon {
  margin: 20px 0;
  width: 100%;
}

#profile-edit-icon td:nth-of-type(1) {
  text-align: center;
}

#profile-edit-icon th:nth-of-type(2), #profile-edit-icon td:nth-of-type(2) {
  width: 30%;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>キャラクター設定</h1>

<form id="profile-edit-form" method="post">
  <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

  <section>
    <h2>キャラクターリスト関連</h2>

    <section class="form">
      <div class="form-title">キャラクターの短縮名（<?=$GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH']?>文字まで）</div>
      <input id="input-nickname" name="nickname" class="form-input" type="text" placeholder="短縮名" value="<?=htmlspecialchars($data['nickname'])?>">
    </section>

    <section class="form">
      <div class="form-title">タグ（<?=$GAME_CONFIG['CHARACTER_TAG_MAX']?>個、各タグ<?=$GAME_CONFIG['CHARACTER_TAG_MAX_LENGTH']?>文字まで）</div>
      <div class="form-description">
        半角スペースで区切ることで複数指定できます。
      </div>
      <input id="input-tags" name="tags" class="form-input-long" type="text" placeholder="タグ" value="<?=htmlspecialchars($data['tags'])?>">
    </section>

    <section class="form">
      <div class="form-title">サマリー（<?=$GAME_CONFIG['CHARACTER_SUMMARY_MAX_LENGTH']?>文字まで）</div>
      <div class="form-description">
        キャラクターリストで表示される短い文章です。
      </div>
      <input id="input-summary" name="summary" class="form-input-long" type="text" placeholder="タグ" value="<?=htmlspecialchars($data['summary'])?>">
    </section>
  </section>

  <section>
    <h2>キャラクターリスト関連</h2>

    <section class="form">
      <div class="form-title">キャラクターのフルネーム（<?=$GAME_CONFIG['CHARACTER_NAME_MAX_LENGTH']?>文字まで）</div>
      <input id="input-name" name="name" class="form-input" type="text" placeholder="フルネーム" value="<?=htmlspecialchars($data['name'])?>">
    </section>

    <section class="form">
      <div class="form-title">プロフィール画像（横400px 縦600px）</div>
      <div class="form-description">
        改行することで複数指定できます。複数指定した場合ランダムで表示されます。
      </div>
      <textarea name="profile_images" class="form-textarea" placeholder="プロフィール画像"><?=htmlspecialchars($data['profile_images'])?></textarea>
    </section>

    <section class="form">
      <div class="form-title">プロフィール文（<?=$GAME_CONFIG['CHARACTER_PROFILE_MAX_LENGTH']?>文字まで）</div>
      <div class="form-description">
        プロフィール文は指定のタグで囲むことで装飾することができます。<br>
        詳しくはルールブックを確認してください。
      </div>
      <textarea id="input-profile" name="profile" class="form-textarea" placeholder="プロフィール文"><?=htmlspecialchars($data['profile'])?></textarea>
    </section>
  </section>

  <section>
    <h2>アイコン</h2>

    一番上に指定したアイコンはキャラクターリストで表示されます。<br>
    アイコン名もURLも登録されていない項目は上に詰められます。
<?php if ($additionalIcons['additional_icons']) { ?>
    <br>追加アイコン枠（<?=$GAME_CONFIG['CHARACTER_ICON_MAX']+1?>番以降）に登録されたアイコンはプロフィール欄では表示されません。
<?php } ?>

    <table id="profile-edit-icon">
      <thead>
        <tr>
          <th>番号</th>
          <th>アイコン名</th>
          <th>URL</th>
        </tr>
      </thead>
      <tbody>
      <?php
        for ($i = 0; $i < $GAME_CONFIG['CHARACTER_ICON_MAX'] + $additionalIcons['additional_icons']; $i++) {
      ?>
        <tr>
          <td>
            <?=$i+1?>.
          </td>
          <td>
            <input name="icon-<?=$i?>-name" class="form-input-table" value="<?= isset($icons[$i]['name']) ? htmlspecialchars($icons[$i]['name']) : '' ?>">
          </td>
          <td>
            <input name="icon-<?=$i?>-url" class="form-input-table" value="<?= isset($icons[$i]['url']) ? htmlspecialchars($icons[$i]['url']) : '' ?>">
          </td>
        </tr>
      <?php
        }
      ?>
      </tbody>
    </table>
  </section>

  <div id="error-message-area"></div>

  <div class="button-wrapper">
    <button class="button" type="submit">更新</button>
  </div>
</form>

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

  $('#profile-edit-form').submit(function(){
    // 各種の値を取得
    var inputName     = $('#input-name').val();
    var inputNickname = $('#input-nickname').val();
    var inputSummary  = $('#input-summary').val();
    var inputTags     = $('#input-tags').val();
    var inputProfile  = $('#input-profile').val();

    // 入力値検証
    // フルネームが入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputName) {
      showErrorMessage('フルネームが入力されていません');
      return false;
    }
    // フルネームが長すぎる場合エラーメッセージを表示して送信を中断
    if (inputName.length > <?=$GAME_CONFIG['CHARACTER_NAME_MAX_LENGTH']?>) {
      showErrorMessage('フルネームが長すぎます');
      return false;
    }

    // 短縮名が入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputNickname) {
      showErrorMessage('短縮名が入力されていません');
      return false;
    }
    // 短縮名が長すぎる場合エラーメッセージを表示して送信を中断
    if (inputNickname.length > <?=$GAME_CONFIG['CHARACTER_NICKNAME_MAX_LENGTH']?>) {
      showErrorMessage('短縮名が長すぎます');
      return false;
    }

    // サマリーが長すぎる場合エラーメッセージを表示して送信を中断
    if (inputSummary.length > <?=$GAME_CONFIG['CHARACTER_SUMMARY_MAX_LENGTH']?>) {
      showErrorMessage('サマリーが長すぎます');
      return false;
    }

    // タグの検証
    var tags = inputTags.split(' ');

    // タグの数が多すぎる場合エラーメッセージを表示して送信を中断
    if (tags.length > <?=$GAME_CONFIG['CHARACTER_TAG_MAX']?>) {
      showErrorMessage('タグの数が多すぎます');
      return false;
    }

    // タグに長すぎるものがある場合エラーメッセージを表示して送信を中断
    tags.forEach(function(tag) {
      if (tag.length > <?=$GAME_CONFIG['CHARACTER_TAG_MAX_LENGTH']?>) {
        showErrorMessage('文字数制限を超過したタグがあります');
        return false;
      }
    });

    // プロフィールが長すぎる場合エラーメッセージを表示して送信を中断
    if (inputProfile.length > <?=$GAME_CONFIG['CHARACTER_PROFILE_MAX_LENGTH']?>) {
      showErrorMessage('プロフィールが長すぎます');
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