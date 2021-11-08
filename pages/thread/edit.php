<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';

  // POSTリクエスト時の処理
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['type']) && $_POST['type'] == 'thread') {
      // 送信タイプがthreadの場合の処理
      // 入力値検証
      if (
        !validatePOST('state',    []) ||
        !validatePOST('password', ['non-empty']) ||
        !validatePOST('target',   ['non-empty', 'natural-number']) ||
        !validatePOST('title',    ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_TITLE_MAX_LENGTH'])   ||
        !validatePOST('name',     ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_NAME_MAX_LENGTH'])    ||
        !validatePOST('message',  ['non-empty'               , 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH'])
      )  {
        responseError(400);
      }

      // ステートの値がopenでもclosedでもdeletedでもない場合400(Bad Request)を返し処理を中断
      if (
        $_POST['state'] != 'open'    &&
        $_POST['state'] != 'closed'  &&
        $_POST['state'] != 'deleted'
      ) {
        responseError(400);
      }
      
      // 対象のスレッドを取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `id`,
          `board`,
          `password`,
          `administrator`
        FROM
          `threads`
        WHERE
          `id` = :target AND `state` != 'deleted';
      ");

      $statement->bindParam(':target', $_POST['target']);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        responseError(500);
      }

      $thread = $statement->fetch(); // 実行結果を取得

      if (!$thread) {
        // 結果が見つからない場合は404(Not Found)を返し処理を中断
        responseError(404);
      }

      if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR && $thread['administrator']) {
        // 管理者でないユーザーが管理者による投稿を編集しようとしていた場合、403(Forbidden)を返し処理を中断
        responseError(403);
      }

      if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR && !password_verify($_POST['password'], $thread['password'])) {
        // ゲーム管理者としてログインしていない場合、編集パスワードの照合を行い合致しない場合は403(Forbidden)を返し処理を中断
        responseError(403);
      }

      // スレッドのアップデート
      $statement = $GAME_PDO->prepare("
        UPDATE
          `threads`
        SET
          `title`      = :title,
          `name`       = :name,
          `message`    = :message,
          `state`      = :state,
          `updated_at` = CURRENT_TIMESTAMP
        WHERE
          `id` = :target;
      ");

      $statement->bindParam(':target',  $_POST['target']);
      $statement->bindParam(':title',   $_POST['title']);
      $statement->bindParam(':name',    $_POST['name']);
      $statement->bindParam(':message', $_POST['message']);
      $statement->bindParam(':state',   $_POST['state']);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        responseError(500); 
      }

      if ($_POST['state'] == 'deleted') {
        header('Location:'.$GAME_CONFIG['URI'].'board?board='.$thread['board'], true, 302); // 削除なら該当の掲示板にリダイレクト
        exit;
      } else {
        header('Location:'.$GAME_CONFIG['URI'].'thread?id='.$_POST['target'], true, 302); // 削除でないなら編集したスレッドにリダイレクト
        exit;
      }
    } else if (isset($_POST['type']) && $_POST['type'] == 'response') {
      // 送信タイプがresponseの場合の処理
      // 入力値検証
      if (
        !validatePOST('state',    []) ||
        !validatePOST('password', ['non-empty']) ||
        !validatePOST('target',   ['non-empty', 'natural-number']) ||
        !validatePOST('name',     ['non-empty', 'single-line', 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_NAME_MAX_LENGTH'])    ||
        !validatePOST('message',  ['non-empty'               , 'disallow-special-chars', 'disallow-space-only'], $GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH'])
      )  {
        responseError(400);
      }
      
      // ステートの値がasisでもdeletedでもない場合400(Bad Request)を返し処理を中断
      if (
        $_POST['state'] != 'asis'    &&
        $_POST['state'] != 'deleted'
      ) {
        responseError(400);
      }
      
      // 対象のレスポンスを取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `id`,
          `thread`,
          `password`,
          `administrator`
        FROM
          `threads_responses`
        WHERE
          `id` = :target AND `deleted` = false;
      ");

      $statement->bindParam(':target', $_POST['target']);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        responseError(500); 
      }

      $response = $statement->fetch(); // 実行結果を取得

      if (!$response) {
        // 結果が見つからない場合は404(Not Found)を返し処理を中断
        responseError(404);
      }

      if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR && $response['administrator']) {
        // 管理者でないユーザーが管理者による投稿を編集しようとしていた場合、403(Forbidden)を返し処理を中断
        responseError(403);
      }

      if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR && !password_verify($_POST['password'], $response['password'])) {
        // ゲーム管理者としてログインしていない場合、編集パスワードの照合を行い合致しない場合は403(Forbidden)を返し処理を中断
        responseError(403);
      }

      // レスポンスのアップデート
      $statement = $GAME_PDO->prepare("
        UPDATE
          `threads_responses`
        SET
          `name`       = :name,
          `message`    = :message,
          `deleted`    = :deleted,
          `updated_at` = CURRENT_TIMESTAMP
        WHERE
          `id` = :target;
      ");

      $statement->bindParam(':target',  $_POST['target']);
      $statement->bindParam(':name',    $_POST['name']);
      $statement->bindParam(':message', $_POST['message']);
      $statement->bindValue(':deleted', $_POST['state'] == 'deleted', PDO::PARAM_BOOL);

      $result = $statement->execute();

      if (!$result) {
        // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
        responseError(500);
      }

      header('Location:'.$GAME_CONFIG['URI'].'thread?id='.$response['thread'], true, 302); // 編集したスレッドにリダイレクト
      exit;
    } else {
      // どちらでもない場合400(Bad Request)を返して処理を中断
      responseError(400);
    }
  }

  // 編集タイプの指定
  if (validateGET('thread', ['non-empty', 'natural-number'])) {
    // threadが指定されており、数値として解釈可能な場合
    $editType = 'thread';
  } else if (validateGET('response', ['non-empty', 'natural-number'])) {
    // responseが指定されており、数値として解釈可能な場合
    $editType = 'response';
  } else {
    // どちらでもない場合400(Bad Request)を返して処理を中断
    responseError(400);
  }

  if ($editType == 'thread') {
    // 編集タイプがスレッドの場合、対象のスレッドを取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `id`,
        `title`,
        `name`,
        `message`,
        `board`,
        `state`
      FROM
        `threads`
      WHERE
        `id` = :thread AND `state` != 'deleted';      
    ");

    $statement->bindParam(':thread', $_GET['thread']);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    $data = $statement->fetch();

    if (!$data) {
      responseError(404); // 取得に失敗した場合は404(Not Found)を返して処理を中断
    }
  } else if ($editType == 'response') {
    // 編集タイプがレスポンスの場合、対象のレスポンスを取得
    $statement = $GAME_PDO->prepare("
      SELECT
        `id`,
        `thread`,
        `name`,
        `message`
      FROM
        `threads_responses`
      WHERE
        `id` = :response AND `deleted` = false;      
    ");

    $statement->bindParam(':response', $_GET['response']);

    $result = $statement->execute();

    if (!$result) {
      responseError(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    }

    $data = $statement->fetch();

    if (!$data) {
      responseError(404); // 取得に失敗した場合は404(Not Found)を返して処理を中断
    }
  }

  $PAGE_SETTING['TITLE'] = $editType == 'thread' ? 'スレッド編集' : 'レス編集';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1><?= $editType == 'thread' ? 'スレッド編集' : 'レス編集' ?></h1>

<section>
<?php if ($editType == 'thread') { ?>
  <section class="form">
    <div class="form-title">ステート</div>
    <select id="input-state" class="form-input" name="state">
      <option value="<?=$data['state']?>">変更しない</option>
      <option value="open">オープン</option>
      <option value="closed">済</option>
      <option value="deleted">削除</option>
    </select>
  </section>
<?php } else if ($editType == 'response') { ?>
  <section class="form">
    <div class="form-title">削除</div>
    <select id="input-state" class="form-input" name="state">
      <option value="asis">削除しない</option>
      <option value="deleted">削除する</option>
    </select>
  </section>
<?php } ?>

<?php if ($editType == 'thread') { ?>
  <section class="form">
    <div class="form-title">タイトル（<?=$GAME_CONFIG['THREAD_TITLE_MAX_LENGTH']?>文字まで）</div>
    <input id="input-title" name="title" class="form-input" type="name" placeholder="タイトル" value="<?=htmlspecialchars($data['title'])?>">
  </section>
<?php } ?>

  <section class="form">
    <div class="form-title">投稿者名（<?=$GAME_CONFIG['THREAD_NAME_MAX_LENGTH']?>文字まで）</div>
    <input id="input-name" name="name" class="form-input" type="name" placeholder="投稿者名" value="<?=htmlspecialchars($data['name'])?>">
  </section>

  <section class="form">
    <div class="form-title">本文（<?=$GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH']?>文字まで）</div>
    <textarea id="input-message" name="message" class="form-textarea" placeholder="本文"><?=htmlspecialchars($data['message'])?></textarea>
  </section>

<?php if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR) { ?>
  <section class="form">
    <div class="form-title">編集パスワード</div>
    <input id="input-password" name="password" class="form-input" type="password" placeholder="編集パスワード">
  </section>
<?php } ?>

  <div id="error-message-area"></div>

  <form id="form" method="post">
    <input type="hidden" name="type" value="<?=$editType?>">
    <input type="hidden" name="target" value="<?=$data['id']?>">
    <input id="input-hidden-state" type="hidden" name="state">
<?php if ($editType == 'thread') { ?>
    <input id="input-hidden-title" type="hidden" name="title">
<?php } ?>
    <input id="input-hidden-name" type="hidden" name="name">
    <input id="input-hidden-message" type="hidden" name="message">
    <input id="input-hidden-password" type="hidden" name="password">

    <div class="button-wrapper">
      <button class="button">編集</button>
    </div>
  </form>
</section>

<script src="<?=$GAME_CONFIG['URI']?>scripts/jssha-sha256.js"></script>
<script>
  var waitingResponse = false; // レスポンス待ちかどうか（多重送信防止）
  var hashStretch = <?=$GAME_CONFIG['CLIENT_HASH_STRETCH']?>; // ハッシュ化の回数
  var hashSalt    = '<?=$GAME_CONFIG['CLIENT_HASH_SALT']?>';  // ハッシュ化の際のsalt

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

  $('#form').submit(function(){
    // 各種の値を取得
    var inputState    = $('#input-state').val();
<?php if ($editType == 'thread') { ?>
    var inputTitle    = $('#input-title').val();
<?php } ?>
    var inputName     = $('#input-name').val();
    var inputMessage  = $('#input-message').val();
    var inputPassword = $('#input-password').val();

    // 入力値検証
<?php if ($editType == 'thread') { ?>
    // タイトルが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputTitle) {
      showErrorMessage('タイトルが入力されていません');
      return false;
    }
    // タイトルが長すぎる場合エラーメッセージを表示して処理を中断
    if (inputTitle.length > <?=$GAME_CONFIG['THREAD_TITLE_MAX_LENGTH']?>) {
      showErrorMessage('タイトルが長すぎます');
      return false;
    }

<?php } ?>
    // 名前が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputName) {
      showErrorMessage('名前が入力されていません');
      return false;
    }
    // 名前が長すぎる場合エラーメッセージを表示して処理を中断
    if (inputName.length > <?=$GAME_CONFIG['THREAD_NAME_MAX_LENGTH']?>) {
      showErrorMessage('名前が長すぎます');
      return false;
    }

    // 本文が入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputMessage) {
      showErrorMessage('本文が入力されていません');
      return false;
    }
    // 本文が長すぎる場合エラーメッセージを表示して処理を中断
    if (inputMessage.length > <?=$GAME_CONFIG['THREAD_MESSAGE_MAX_LENGTH']?>) {
      showErrorMessage('本文が長すぎます');
      return false;
    }

<?php if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR) { ?>
    // 編集パスワードが入力されていない場合エラーメッセージを表示して処理を中断
    if (!inputPassword) {
      showErrorMessage('編集パスワードが入力されていません');
      return false;
    }
<?php } ?>

    // レスポンス待ち中に再度送信しようとした場合アラートを表示して処理を中断
    if (waitingResponse == true) {
      alert("送信中です。しばらくお待ち下さい。");
      return false;
    }

    // パスワードのハッシュ化
    var hashedPassword = inputPassword + hashSalt; // ソルティング
    for (var i = 0; i < hashStretch; i++) { // ストレッチング
      var shaObj = new jsSHA("SHA-256", "TEXT");
      shaObj.update(hashedPassword);
      hashedPassword = shaObj.getHash("HEX");
    }

    // 送信
    $('#input-hidden-state').val(inputState);
<?php if ($editType == 'thread') { ?>
    $('#input-hidden-title').val(inputTitle);
<?php } ?>
    $('#input-hidden-name').val(inputName);
    $('#input-hidden-message').val(inputMessage);
    $('#input-hidden-password').val(hashedPassword);

    waitingResponse = true; // レスポンス待ち状態をONに
  });
</script>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>