<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  // お知らせの取得
  $statement = $GAME_PDO->prepare("
    SELECT
      `title`,
      `message`,
      `announced_at`
    FROM
      `announcements`
    WHERE
      `announced_at` <= CURRENT_TIMESTAMP
    ORDER BY
      `announced_at` DESC
    LIMIT
      :limit;
  ");

  // allが指定されていれば全件(1000万件上限)、指定されていなければデフォルトでは5件取得
  $statement->bindValue(':limit', isset($_GET['all']) ? 10000000 : $GAME_CONFIG['ANNOUNCEMENTS_LIMIT'], PDO::PARAM_INT);

  $result = $statement->execute();

  if (!$result) {
    http_response_code(500); // SQLの実行に失敗した場合は500(Internal Server Error)を返して処理を中断
    exit;
  }

  $announcements = $statement->fetchAll();

  $PAGE_SETTING['TITLE'] = 'お知らせ';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<h1>お知らせ</h1>

<section>
<?php if (!$announcements) { ?>
  <p>まだお知らせはありません。</p>
<?php } else { ?>
<?php foreach ($announcements as $index => $announcement) { ?>
  <section>
    <h2><?=$announcement['title']?><span class="announcement-detail"><?=' / '.date('Y-m-d H:i', strtotime($announcement['announced_at']))?></span></h2>
    <p><?=$announcement['message']?></p>
  </section>
  <hr>
<?php } ?>
  <?php if (!isset($_GET['all'])) { ?>
    <a class="show-all-announcement" href="?all=t">&gt;&gt; お知らせを全件表示</a>
  <?php } ?>
<?php } ?>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>