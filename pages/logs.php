<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  // 現在のページ
  // pageの指定があればその値-1、指定がなければ0
  // インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $page = isset($_GET['page']) ? intval($_GET['page']) -1 : 0;

  // 現在ページのログ一覧を取得
  // ページに応じた範囲のログを検索
  // デフォルトの設定ではページ0であれば0件飛ばして20件、ページ1であれば20件飛ばして20件、ページ2であれば40件飛ばして20件、ページnであればn×20件飛ばして20件を表示します。
  // ただし、次のページがあるかどうか検出するために1件余分に取得します。
  // 21件取得してみて21件取得できれば次のページあり、取得結果の数がそれ未満であれば次のページなし（最終ページ）として扱います。
  $statement = $GAME_PDO->prepare("
    SELECT
      `exploration_logs`.`id`,
      `exploration_stages_master_data`.`title`,
      `exploration_logs`.`leader`,
      `characters_icons`.`url` AS `leader_icon`,
      `exploration_logs`.`timestamp`
    FROM
      `exploration_logs`
    LEFT JOIN
      `characters_icons` ON `characters_icons`.`ENo` = `exploration_logs`.`leader`
    JOIN
      `exploration_stages_master_data` ON `exploration_stages_master_data`.`stage_id` = `exploration_logs`.`stage`
    WHERE
      `exploration_logs`.`leader` = :ENo
    ORDER BY
      `exploration_logs`.`id` DESC
    LIMIT
      :offset, :number;
  ");

  $statement->bindValue(':ENo',    $_SESSION['ENo']);
  $statement->bindValue(':offset', $page * $GAME_CONFIG['EXPLORATION_LOGS_PER_PAGE'], PDO::PARAM_INT);
  $statement->bindValue(':number', $GAME_CONFIG['EXPLORATION_LOGS_PER_PAGE'] + 1,     PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
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
  
  $PAGE_SETTING['TITLE'] = '探索ログ';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.logs {
  border-collapse: collapse;
  margin: 0 auto;
  font-size: 15px;
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
  width: 400px;
}

.logs th:nth-child(3), .logs td:nth-child(3) {
  text-align: center;
}

.logs th:nth-child(4), .logs td:nth-child(4) {
  text-align: center;
  width: 200px;
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

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>探索ログ</h1>

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?page=<?=$page+1+1?>">次のページ</a></div>
</section>

<table class="logs">
  <thead>
    <tr>
      <th>No.</th>
      <th>探索先</th>
      <th>リーダー</th>
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
        <a href="<?=$GAME_CONFIG['URI']?>/logs/<?=$directory.'/'.$log['id'].'.html'?>" target="_blank"><?= $log['title'] ?></a>
      </td>
      <td class="leader">
        <a href="<?=$GAME_CONFIG['URI']?>profile?ENo=<?=$log['leader']?>">
          <?php
            $COMPONENT_ICON['src'] = $log['leader_icon'];
            include GETENV('GAME_ROOT').'/components/icon.php';
          ?>
        </a>
      </td>
      <!--<td class="party">
        <div class="characters-wrapper">
          <a href="" target="_blank">
            <character-icon/>
          </a>
        </div>
      </td>-->
      <td>
        <?= $log['timestamp'] ?>
      </td>
      <td>
        <span>完了</span>
      </td>
    </tr>
<?php } ?>
  </tbody>
</table>

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?page=<?=$page+1+1?>">次のページ</a></div>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>