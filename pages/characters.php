<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  // 現在のページ
  // pageの指定があればその値-1、指定がなければ0
  // インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $page = isset($_GET['page']) ? intval($_GET['page']) -1 : 0;

  // ページが負なら400(Bad Request)を返して処理を中断
  if ($page < 0) {
    http_response_code(400);
    exit;
  }

  // 現在ページのキャラクター一覧を取得
  // 削除フラグが立っておらずENoがページの範囲内のキャラクターを検索
  // デフォルトの設定ではページ0であればENo.1～100、ページ1であればENo.101～200、ページnであればENo.n×100+1～(n+1)×100の範囲を表示します。
  $statement = $GAME_PDO->prepare("
    SELECT
      `characters`.`ENo`,
      `characters`.`nickname`,
      `characters`.`summary`,
      `characters`.`ATK`,
      `characters`.`DEX`,
      `characters`.`MND`,
      `characters`.`AGI`,
      `characters`.`DEF`,
      `characters_icons`.`url` AS `icon`,
      IFNULL(GROUP_CONCAT(DISTINCT `characters_tags`.`tag` ORDER BY `characters_tags`.`id` SEPARATOR ' '), '') AS `tags`
    FROM
      `characters`
    LEFT JOIN
      `characters_icons` ON `characters`.`ENo` = `characters_icons`.`ENo`
    LEFT JOIN
      `characters_tags`  ON `characters`.`ENo` = `characters_tags`.`ENo`
    WHERE
      `characters`.`ENo` BETWEEN :ENoMin AND :ENoMax AND
      `characters`.`deleted` = false
    GROUP BY
		  `characters`.`ENo`, `characters_icons`.`url`;
  ");

  $statement->bindValue(':ENoMin', $page * 100 + 1);
  $statement->bindValue(':ENoMax', ($page+1) * 100);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500);
    exit;
  }

  $characters = $statement->fetchAll();

  // 次のページが存在するかを取得（ページリンク生成用）
  $statement = $GAME_PDO->query('
    SELECT
      `ENo`
    FROM
      `characters`
    WHERE
      `deleted` = false
    ORDER BY
      `ENo` DESC
    LIMIT
      1;
  ');

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    http_response_code(500);
    exit;
  }

  $lastCharacter = $statement->fetch();

  // 取得したデータにENoが含まれていればその値を最大のENoとする
  // そうでない場合（登録されているキャラがいないなどで取得できなかった場合）最大ENoは0とする
  $lastENo = isset($lastCharacter['ENo']) ? $lastCharacter['ENo'] : 0;

  $PAGE_SETTING['TITLE'] = 'キャラクターリスト';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<h1>キャラクターリスト</h1>

<section class="pagelinks">
<?php
  $i = 1;
  $nextCreateLinkENoStart = 1;
  do {
?>
  <a class="pagelink<?php echo $i-1 == $page ? ' pagelink-current' : ''; ?>" href="?page=<?=$i?>"><?=$nextCreateLinkENoStart?>-</a>
<?php
    $i++;
    $nextCreateLinkENoStart += $GAME_CONFIG['CHARACTER_LIST_ITEMS_PER_PAGE'];
  } while($nextCreateLinkENoStart <= $lastENo);
?>
</section>

<ul class="character-list">
<?php foreach ($characters as $character) { ?>
  <li>
    <?php
      $COMPONENT_ICON['src'] = $character['icon'];
      include GETENV('GAME_ROOT').'/components/icon.php';
    ?>
    <div class="character-list-profile">
      <div class="character-list-info">
        <a class="character-list-profile-link" href="<?=$GAME_CONFIG['URI']?>profile?ENo=<?=$character['ENo']?>">
          <span class="character-list-name"><?=htmlspecialchars($character['nickname'])?></span> <span class="character-list-eno">&lt; ENo.<?=$character['ENo']?> &gt;</span>
        </a>
      </div>
      <div class="character-list-tags">
        <?php
          $tags = explode(' ', $character['tags']);
          foreach ($tags as $tag) {
        ?>
        <span class="character-list-tag">
          <?=htmlspecialchars($tag)?>
        </span>
        <?php
          }
        ?>
      </div>
      <div class="character-list-summary">
        <?=htmlspecialchars($character['summary'])?>
      </div>
    </div>
  </li>
<?php } ?>
</ul>

<section class="pagelinks">
<?php
  $i = 1;
  $nextCreateLinkENoStart = 1;
  do {
?>
  <a class="pagelink<?php echo $i-1 == $page ? ' pagelink-current' : ''; ?>" href="?page=<?=$i?>"><?=$nextCreateLinkENoStart?>-</a>
<?php
    $i++;
    $nextCreateLinkENoStart += $GAME_CONFIG['CHARACTER_LIST_ITEMS_PER_PAGE'];
  } while($nextCreateLinkENoStart <= $lastENo);
?>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>
