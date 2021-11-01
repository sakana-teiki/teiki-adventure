<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification_administrator.php';

  require_once GETENV('GAME_ROOT').'/utils/validation.php';
  require_once GETENV('GAME_ROOT').'/utils/notification.php';

  // POSTの場合
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 入力値検証
    if (
      !validatePOST('target',   []) ||
      !validatePOST('eno',      ['natural-number'])       ||
      !validatePOST('discord',  ['non-empty', 'boolean']) ||
      !validatePOST('message',  ['non-empty', 'disallow-special-chars']) ||
      ($_POST['target']  != 'all' && $_POST['target']  != 'particular') // 通知先がallでもparticularでもない
    ) {
      http_response_code(400);
      exit;
    }

    // 特定のENoへの通知の場合    
    if ($_POST['target'] == 'particular') {
      if (!ctype_digit($_POST['eno'])) { // 受け取ったENoが数値として解釈不能な場合400(Bad Request)を返し処理を中断
        http_response_code(400); 
        exit;
      }

      $target = intval($_POST['eno']); // 対象を受け取ったENoに

      // 対象キャラクターの取得
      $statement = $GAME_PDO->prepare("
        SELECT
          `webhook`
        FROM
          `characters`
        WHERE
          `ENo` = :target AND `deleted` = false;
      ");

      $statement->bindParam(':target', $target);

      $result    = $statement->execute();
      $character = $statement->fetch();
          
      if (!$result || !$character) {
        // 実行に失敗した場合もしくはキャラクターの取得に失敗した場合500(Internal Server Error)を返して処理を中断
        http_response_code(500); 
        exit;
      }
    } else {
      $target = null; // 対象をnullに（null=全体）
    }

    // 通知の実行
    $statement = $GAME_PDO->prepare("
      INSERT INTO `notifications` (
        `ENo`,
        `type`,
        `message`
      ) VALUES (
        :target,
        :type,
        :message
      );
    ");

    $statement->bindParam(':target',  $target);
    $statement->bindValue(':type',    'administrator');
    $statement->bindParam(':message', $_POST['message']);

    $result = $statement->execute();
        
    if (!$result) {
      // 実行に失敗した場合500(Internal Server Error)を返して処理を中断
      http_response_code(500); 
      exit;
    }
  
    // 特定のENoへの通知かつDiscord通知を行う場合で、対象キャラクターがwebhookを登録している場合
    if (
      $_POST['target']  == 'particular' &&
      $_POST['discord'] == 'true'       &&
      $character['webhook'] != ''
    ) {
      notifyDiscord($character['webhook'], "管理者からのメッセージがあります。\n".$_POST['message']);
    }
  }

  $PAGE_SETTING['TITLE'] = '通知送信';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>通知送信</h1>

<p>
  指定キャラクターあるいは全体にサイト上で通知を送信します。<br>
  Discord上に通知を送信することも出来ますが、全体に通知を送信する場合はDiscord通知は行えません。
</p>

<section>
  <h2>通知内容</h2>

  <form id="notification-form" method="post">
    <input type="hidden" name="csrf_token" value="<?=$_SESSION['token']?>">

    <section class="form">
      <div class="form-title">通知先</div>
      <select id="input-target" class="form-input" name="target">
        <option value="all">全体</option>
        <option value="particular">特定キャラクター</option>
      </select>
    </section>

    <section class="form">
      <div class="form-title">通知先ENo</div>
      <div class="form-description">通知先のENoを指定します（通知先が特定キャラクターの場合のみ）。</div>
      <input id="input-eno" class="form-input" type="number" name="eno" placeholder="通知先のENo">
      <button id="check-eno" type="button" class="form-check-button">送信先を確認</button>
    </section>

    <section class="form">
      <div class="form-title">Discord通知</div>
      <div class="form-description">Discordにも通知を送信するかを指定します（通知先が特定キャラクターで、対象のキャラクターがWebhookを登録している場合のみ）。</div>
      <select id="input-discord" class="form-input" name="discord">
        <option value="false">行わない</option>
        <option value="true">行う</option>
      </select>
    </section>

    <section class="form">
      <div class="form-title">通知内容</div>
      <input id="input-message" name="message" class="form-input-long" type="text" placeholder="通知内容">
    </section>

    <div class="button-wrapper">
      <button class="button" type="submit">送信</button>
    </div>
  </form>
</section>

<script>
  // 送信先を確認ボタンを押したら新しいタブで指定のENoのプロフィールページを表示
  $('#check-eno').on('click', function(){
    window.open('<?=$GAME_CONFIG['URI']?>profile?ENo=' + $('#input-eno').val());
  });

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

  $('#notification-form').submit(function(){
    // 値を取得
    var inputTitle   = $('#input-title').val();
    var inputMessage = $('#input-message').val();

    // 入力値検証
    // お知らせ内容が入力されていない場合エラーメッセージを表示して送信を中断
    if (!inputMessage) {
      showErrorMessage('通知タイトルが入力されていません');
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