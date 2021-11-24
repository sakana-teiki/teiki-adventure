<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  require GETENV('GAME_ROOT').'/utils/parser.php';

  // 各URLパラメーターの初期値を設定
  $page   = isset($_GET['page'])   ? intval($_GET['page']) -1 : 0;       // 現在のページ。pageの指定があればその値-1、指定がなければ0。インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $target = isset($_GET['target']) ? $_GET['target'] : $_SESSION['ENo']; // 対象のENo。指定がなければ空文字列 
  $mode   = isset($_GET['mode']) && $_GET['mode'] == 'member' ? 'member' : 'leader'; // modeの指定があり、それがmemberなら自身が含まれている、指定がなければ自身がリーダーであるログを検索

  // モードによって取得処理を振り分け
  if ($mode == 'member') {
    // 自身が含まれている現在ページのログ一覧を取得
    // ページに応じた範囲のログを検索
    // デフォルトの設定ではページ0であれば0件飛ばして20件、ページ1であれば20件飛ばして20件、ページ2であれば40件飛ばして20件、ページnであればn×20件飛ばして20件を表示します。
    // ただし、次のページがあるかどうか検出するために1件余分に取得します。
    // 21件取得してみて21件取得できれば次のページあり、取得結果の数がそれ未満であれば次のページなし（最終ページ）として扱います。
    $statement = $GAME_PDO->prepare("
      SELECT
        `exploration_logs`.`id`,
        `exploration_stages_master_data`.`title`,
        GROUP_CONCAT(
          `exploration_logs_members`.`member`,
          '\n',
          IFNULL((SELECT `url` FROM `characters_icons` WHERE `characters_icons`.`ENo` = `exploration_logs_members`.`member` LIMIT 1), '')
          
          SEPARATOR '\n'
        ) as `members`,
        `exploration_logs`.`result`,
        `exploration_logs`.`timestamp`
      FROM
        `exploration_logs_members` AS `elm`
      JOIN
        `exploration_logs` ON `exploration_logs`.`id` = `elm`.`log`
      JOIN
        `exploration_stages_master_data` ON `exploration_stages_master_data`.`stage_id` = `exploration_logs`.`stage`
      JOIN
        `exploration_logs_members` ON `exploration_logs_members`.`log` = `exploration_logs`.`id`
      WHERE
        `elm`.`member` = :ENo
      GROUP BY
        `exploration_logs`.`id`
      ORDER BY
        `exploration_logs`.`id` DESC
      LIMIT
        :offset, :number;
    ");

    $statement->bindValue(':ENo',    $target);
    $statement->bindValue(':offset', $page * $GAME_CONFIG['EXPLORATION_LOGS_PER_PAGE'], PDO::PARAM_INT);
    $statement->bindValue(':number', $GAME_CONFIG['EXPLORATION_LOGS_PER_PAGE'] + 1,     PDO::PARAM_INT);

    $result = $statement->execute();

    if (!$result) {
      // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      responseError(500);
    }
  } else {
    // 自身がリーダーの現在ページのログ一覧を取得
    // ページに応じた範囲のログを検索
    // デフォルトの設定ではページ0であれば0件飛ばして20件、ページ1であれば20件飛ばして20件、ページ2であれば40件飛ばして20件、ページnであればn×20件飛ばして20件を表示します。
    // ただし、次のページがあるかどうか検出するために1件余分に取得します。
    // 21件取得してみて21件取得できれば次のページあり、取得結果の数がそれ未満であれば次のページなし（最終ページ）として扱います。
    $statement = $GAME_PDO->prepare("
      SELECT
        `exploration_logs`.`id`,
        `exploration_stages_master_data`.`title`,
        GROUP_CONCAT(
          `exploration_logs_members`.`member`,
          '\n',
          IFNULL((SELECT `url` FROM `characters_icons` WHERE `characters_icons`.`ENo` = `exploration_logs_members`.`member` LIMIT 1), '')
          
          SEPARATOR '\n'
        ) as `members`,
        `exploration_logs`.`result`,
        `exploration_logs`.`timestamp`
      FROM
        `exploration_logs`
      JOIN
        `exploration_stages_master_data` ON `exploration_stages_master_data`.`stage_id` = `exploration_logs`.`stage`
      JOIN
        `exploration_logs_members` ON `exploration_logs_members`.`log` = `exploration_logs`.`id`
      WHERE
        `exploration_logs`.`leader` = :ENo
      GROUP BY
        `exploration_logs`.`id`
      ORDER BY
        `exploration_logs`.`id` DESC
      LIMIT
        :offset, :number;
    ");

    $statement->bindValue(':ENo',    $target);
    $statement->bindValue(':offset', $page * $GAME_CONFIG['EXPLORATION_LOGS_PER_PAGE'], PDO::PARAM_INT);
    $statement->bindValue(':number', $GAME_CONFIG['EXPLORATION_LOGS_PER_PAGE'] + 1,     PDO::PARAM_INT);

    $result = $statement->execute();

    if (!$result) {
      // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
      responseError(500);
    }
  }


  $logs = $statement->fetchAll();

  // 1件余分に取得できていれば次のページありとして余分な1件を切り捨て
  if (count($logs) == $GAME_CONFIG['EXPLORATION_LOGS_PER_PAGE'] + 1) {
    $existsNext = true;
    array_pop($logs);
  } else {
    // 取得件数が足りなければ次のページなしとする
    $existsNext = false;
  }

  // 結果表示用
  $resultText = array(
    'win'  => '勝利',
    'lose' => '敗北',
    'even' => '引分'
  );
  
  $PAGE_SETTING['TITLE'] = '探索ログ';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.logs {
  border-collapse: collapse;
  margin: 0 auto;
  font-size: 15px;
  width: 100%;
}

.logs th {
  background-color: #444;
  border: 1px solid #F8F8F8;
  color: #EEE;
  font-family: 'BIZ UDPGothic', 'Hiragino Kaku Gothic Pro', 'ヒラギノ角ゴ Pro W3', Meiryo, 'ＭＳ Ｐゴシック', sans-serif;
}

.logs td {
  border: 1px solid #F8F8F8;
  border-bottom: 1px solid lightgray;
}

.logs th:nth-child(1), .logs td:nth-child(1) {
  text-align: center;
  width: 35px;
}

.logs th:nth-child(2), .logs td:nth-child(2) {
  text-align: center;
}

.logs th:nth-child(3), .logs td:nth-child(3) {
  text-align: center;
  width: 300px;
}

.logs th:nth-child(4), .logs td:nth-child(4) {
  text-align: center;
  width: 100px;
}

.logs th:nth-child(5), .logs td:nth-child(5) {
  text-align: center;
  width: 60px;
}

.logs a {
  text-decoration: none;
  font-weight: bold;
  color: #444;
}

.icon-links {
  display: flex;
}

.search-input {
  width: 80px;
  margin-right: 10px;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>探索ログ</h1>

<form method="get">
  <h2>検索条件</h2>
  
  <label>
    対象ENo
    <input class="search-input" type="number" name="target" value="<?=htmlspecialchars($target)?>" min="1">
  </label>
    
  <label>
    モード
    <select name="mode">
      <option value="leader"<?= $mode == 'leader' ? ' selected': ''?>>自身がリーダーのログ</option>
      <option value="member"<?= $mode == 'member' ? ' selected': ''?>>自身がメンバーに含まれているログ</option>
    </select>
  </label>

  <button type="submit">検索</button>
</form>

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?target=<?=htmlspecialchars($target)?>&mode=<?=htmlspecialchars($mode)?>&page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?target=<?=htmlspecialchars($target)?>&mode=<?=htmlspecialchars($mode)?>&page=<?=$page+1-1?>">次のページ</a></div>
</section>

<table class="logs">
  <thead>
    <tr>
      <th>No.</th>
      <th>探索先</th>
      <th>メンバー</th>
      <th>探索時刻</th>
      <th>結果</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($logs as $log) { ?>
    <tr>
      <td>
        <?= $log['id'] ?>
      </td>
      <td>
        <?php
          $directory = strval(floor($log['id']/10000) + 1); // ディレクトリ名を計算
        ?>
        <a href="<?=$GAME_CONFIG['URI']?>logs/<?=$directory.'/'.$log['id'].'.html'?>" target="_blank"><?= $log['title'] ?></a>
      </td>
      <td class="party">
        <div class="icon-links">
<?php 
  $members = parseMemberIconsResult($log['members']);
  foreach($members as $member) {
?>
          <a href="<?=$GAME_CONFIG['URI']?>profile?ENo=<?=$member['ENo']?>">
            <?php
              $COMPONENT_ICON['src'] = $member['icon'];
              include GETENV('GAME_ROOT').'/components/icon.php';
            ?>
          </a>
<?php
  }
?>
        </div>
      </td>
      <td>
        <?= $log['timestamp'] ?>
      </td>
      <td>
        <span><?=$resultText[$log['result']]?></span>
      </td>
    </tr>
<?php } ?>
  </tbody>
</table>

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?target=<?=htmlspecialchars($target)?>&mode=<?=htmlspecialchars($mode)?>&page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?target=<?=htmlspecialchars($target)?>&mode=<?=htmlspecialchars($mode)?>&page=<?=$page+1-1?>">次のページ</a></div>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>