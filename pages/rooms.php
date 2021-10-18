<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';
  require GETENV('GAME_ROOT').'/middlewares/verification.php';

  // 現在のページ
  // pageの指定があればその値-1、指定がなければ0
  // インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $page = isset($_GET['page']) ? intval($_GET['page']) -1 : 0;

  // ページが負なら400(Bad Request)を返して処理を中断
  if ($page < 0) {
    http_response_code(400); 
    exit;
  }

  // 現在ページのトークルームを取得
  // 削除フラグが立っておらずページに応じた範囲のトークルームを検索
  // デフォルトの設定ではページ0であれば0件飛ばして30件、ページ1であれば30件飛ばして30件、ページ2であれば60件飛ばして30件、ページnであればn×30件飛ばして30件を表示します。
  // ただし、次のページがあるかどうか検出するために1件余分に取得します。
  // 31件取得してみて31件取得できれば次のページあり、取得結果の数がそれ未満であれば次のページなし（最終ページ）として扱います。
  $statement = $GAME_PDO->prepare('
    SELECT
      `rooms`.`RNo`,
      `rooms`.`administrator`,
      `rooms`.`title`,
      `rooms`.`summary`,
      `rooms`.`last_posted_at`,
      IFNULL(GROUP_CONCAT(DISTINCT `rooms_tags`.`tag` ORDER BY `rooms_tags`.`id` SEPARATOR " "), "") AS `tags`
    FROM
      `rooms`
    LEFT JOIN
      `rooms_tags` ON `rooms`.`RNo` = `rooms_tags`.`RNo`
    WHERE
      `rooms`.`administrator` IS NOT NULL AND
      `rooms`.`deleted` = false
    GROUP BY
		  `rooms`.`RNo`
    ORDER BY
      `rooms`.`last_posted_at` DESC
    LIMIT
      :offset, :number;
  ');

  $statement->bindValue(':offset', $page * $GAME_CONFIG['ROOM_LIST_ITEMS_PER_PAGE'], PDO::PARAM_INT);
  $statement->bindValue(':number', $GAME_CONFIG['ROOM_LIST_ITEMS_PER_PAGE'] + 1,     PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500); 
    exit;
  }

  $rooms = $statement->fetchAll();

  // 1件余分に取得できていれば次のページありとして余分な1件を切り捨て
  if (count($rooms) == $GAME_CONFIG['ROOM_LIST_ITEMS_PER_PAGE'] + 1) {
    $existsNext = true;
    array_pop($rooms);
  } else {
  // 取得件数が足りなければ次のページなしとする
    $existsNext = false;
  }

  $PAGE_SETTING['TITLE'] = 'トークルーム一覧';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<h1>トークルーム一覧</h1>

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?page=<?=$page+1+1?>">次のページ</a></div>
</section>

<ul class="room-list">
<?php foreach ($rooms as $room) { ?>
  <li>
    <div class="room-list-info">
      <a class="room-list-link" href="<?=$GAME_CONFIG['URI']?>room?RNo=<?=$room['RNo']?>">
        <span class="room-list-title"><?=htmlspecialchars($room['title'])?></span>
        <span class="room-list-rno">&lt; RNo.<?=$room['RNo']?> &gt;</span>
      </a>
    </div>
    <div class="room-list-tags">
      <?php 
        $tags = explode(' ', $room['tags']);
        foreach ($tags as $tag) {
      ?>
      <span class="room-list-tag">
        <?=htmlspecialchars($tag)?>
      </span>
      <?php
        }
      ?>
    </div>
    <div class="room-list-summary">
      <?=htmlspecialchars($room['summary'])?>
    </div>
    <div class="room-list-meta">
      最終投稿：<?=$room['last_posted_at']?>
    </div>
  </li>
<?php } ?>
</ul>

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?page=<?=$page+1+1?>">次のページ</a></div>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>