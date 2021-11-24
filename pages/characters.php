<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  // 現在のページ
  // pageの指定があればその値-1、指定がなければ0
  // インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $page = isset($_GET['page']) ? intval($_GET['page']) -1 : 0;

  // ページが負なら400(Bad Request)を返して処理を中断
  if ($page < 0) {
    responseError(400);
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
      IFNULL((SELECT `url` FROM `characters_icons` WHERE `characters_icons`.`ENo` = `characters`.`ENo` LIMIT 1), '') AS `icon`,
      IFNULL(GROUP_CONCAT(DISTINCT `characters_tags`.`tag` ORDER BY `characters_tags`.`id` SEPARATOR ' '), '') AS `tags`
    FROM
      `characters`
    LEFT JOIN
      `characters_tags`  ON `characters`.`ENo` = `characters_tags`.`ENo`
    WHERE
      `characters`.`ENo` BETWEEN :ENoMin AND :ENoMax AND
      `characters`.`deleted` = false
    GROUP BY
      `characters`.`ENo`,
      `characters`.`nickname`,
      `characters`.`summary`,
      `characters`.`ATK`,
      `characters`.`DEX`,
      `characters`.`MND`,
      `characters`.`AGI`,
      `characters`.`DEF`;
  ");

  $statement->bindValue(':ENoMin', $page * 100 + 1);
  $statement->bindValue(':ENoMax', ($page+1) * 100);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
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
    responseError(500);
  }

  $lastCharacter = $statement->fetch();

  // 取得したデータにENoが含まれていればその値を最大のENoとする
  // そうでない場合（登録されているキャラがいないなどで取得できなかった場合）最大ENoは0とする
  $lastENo = isset($lastCharacter['ENo']) ? $lastCharacter['ENo'] : 0;

  $PAGE_SETTING['TITLE'] = 'キャラクターリスト';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.character-list {
  list-style: none;
  border-top: 1px solid lightgray;
  margin: 0 20px;
}

.character-list-icon {
  flex-shrink: 0;
}

.character-list li {
  border-bottom: 1px solid lightgray;
  display: flex;
  margin: 0;
  padding: 10px;
}

.character-list-profile {
  padding-left: 10px;
  width: 100%;
}

.character-list-profile-link {
  text-decoration: none;
}

.character-list-name {
  font-size: 16px;
  font-weight: bold;
  color: #222222;
}

.character-list-eno {
  font-size: 13px;
  margin-left: 3px;
  color: gray;
}

.character-list-tags {
  font-size: 14px;
  text-decoration: none;
  color: gray;
}

.character-list-statuses {
  display: flex;
  width: 100%;
  border-bottom: 1px solid lightgray;
}

.character-list-status {
  display: flex;
  margin: 0 20px 4px 0;
}

.character-list-status-name {
  font-weight: bold;
  color: #555;
  margin-right: 10px;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

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
    <div class="character-list-icon">
      <?php
        $COMPONENT_ICON['src'] = $character['icon'];
        include GETENV('GAME_ROOT').'/components/icon.php';
      ?>
    </div>
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
      <div class="character-list-statuses">
        <div class="character-list-status">
          <div class="character-list-status-name">
            ATK
          </div>
          <div class="character-list-status-value">
            <?=$character['ATK']?>
          </div>
        </div>
        <div class="character-list-status">
          <div class="character-list-status-name">
            DEX
          </div>
          <div class="character-list-status-value">
            <?=$character['DEX']?>
          </div>
        </div>
        <div class="character-list-status">
          <div class="character-list-status-name">
            MND
          </div>
          <div class="character-list-status-value">
            <?=$character['MND']?>
          </div>
        </div>
        <div class="character-list-status">
          <div class="character-list-status-name">
            AGI
          </div>
          <div class="character-list-status-value">
            <?=$character['AGI']?>
          </div>
        </div>
        <div class="character-list-status">
          <div class="character-list-status-name">
            DEF
          </div>
          <div class="character-list-status-value">
            <?=$character['DEF']?>
          </div>
        </div>
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
