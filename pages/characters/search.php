<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  // 各URLパラメーターの初期値を設定
  $page  = isset($_GET['page'])  ? intval($_GET['page']) -1 : 0; // 現在のページ。pageの指定があればその値-1、指定がなければ0。インデックス値のように扱いたいため内部的には受け取った値-1をページとします。
  $name  = isset($_GET['name'])  ? $_GET['name']  : '';        // 検索対象のキャラクター名。指定がなければ空文字列 
  $tag   = isset($_GET['tag'])   ? $_GET['tag']   : '';        // 検索対象のタグ。指定がなければ空文字列
  $skill = isset($_GET['skill']) ? $_GET['skill'] : '';        // 検索対象のスキル名。指定がなければ空文字列
  $order = isset($_GET['order']) ? $_GET['order'] : 'default'; // 並び順。指定がなければデフォルト
  $mode  = isset($_GET['mode'])  ? $_GET['mode']  : 'default'; // 絞り込み。指定がなければデフォルト

  // ページが負なら400(Bad Request)を返して処理を中断
  if ($page < 0) {
    responseError(400);
  }

  // $orderの値が以下のどれでもないなら400(Bad Request)を返して処理を中断
  if (
    ($order !== 'default')  &&
    ($order !== 'ENo-ASC')  &&
    ($order !== 'ENo-DESC') &&
    ($order !== 'ATK-ASC')  &&
    ($order !== 'ATK-DESC') &&
    ($order !== 'DEX-ASC')  &&
    ($order !== 'DEX-DESC') &&
    ($order !== 'MND-ASC')  &&
    ($order !== 'MND-DESC') &&
    ($order !== 'AGI-ASC')  &&
    ($order !== 'AGI-DESC') &&
    ($order !== 'DEF-ASC')  &&
    ($order !== 'DEF-DESC')
  ) {
    responseError(400);
  }

  // $modeの値が以下のどれでもないなら400(Bad Request)を返して処理を中断
  if (
    ($mode !== 'default') &&
    ($mode !== 'fav')     &&
    ($mode !== 'faved')
  ) {
    responseError(400);
  }

  // ゲームにログインしていないなら強制的に絞り込み条件をdefaultに
  if (!$GAME_LOGGEDIN) {
    $mode = 'default';
  }

  // 名前に応じた条件文を指定
  if ($name === '') {
    $nameStatement = ' true ';
  } else {
    $nameStatement = ' `characters`.`name` like :name ';
  }

  // タグに応じた条件文を指定
  if ($tag === '') {
    $tagStatement = ' true ';
  } else {
    $tagStatement = ' `tags` like :tag ';
  }

  // スキル名に応じた条件文を指定
  if ($skill === '') {
    $skillStatement = ' true ';
  } else {
    $skillStatement = ' `skills` like :skill ';
  }

  // 絞り込みに応じた条件文を指定
  if ($mode === 'default') {
    $modeStatement = ' true ';
  } else if ($mode === 'fav') {
    $modeStatement = ' `characters`.`ENo` IN (SELECT `cf`.`faved` FROM `characters_favs` AS `cf` WHERE `cf`.`faver` = :ENo) ';
  } else if ($mode === 'faved') {
    $modeStatement = ' `characters`.`ENo` IN (SELECT `cf`.`faver` FROM `characters_favs` AS `cf` WHERE `cf`.`faved` = :ENo) ';
  }

  // 並び順に応じた条件文を指定
  switch ($order) {
    case 'default' : $orderStatement = ' `characters`.`ENo` ASC ';  break;
    case 'ENo-ASC' : $orderStatement = ' `characters`.`ENo` ASC ';  break;
    case 'ENo-DESC': $orderStatement = ' `characters`.`ENo` DESC '; break;
    case 'ATK-ASC' : $orderStatement = ' `characters`.`ATK` ASC ';  break;
    case 'ATK-DESC': $orderStatement = ' `characters`.`ATK` DESC '; break;
    case 'DEX-ASC' : $orderStatement = ' `characters`.`DEX` ASC ';  break;
    case 'DEX-DESC': $orderStatement = ' `characters`.`DEX` DESC '; break;
    case 'MND-ASC' : $orderStatement = ' `characters`.`MND` ASC ';  break;
    case 'MND-DESC': $orderStatement = ' `characters`.`MND` DESC '; break;
    case 'AGI-ASC' : $orderStatement = ' `characters`.`AGI` ASC ';  break;
    case 'AGI-DESC': $orderStatement = ' `characters`.`AGI` DESC '; break;
    case 'DEF-ASC' : $orderStatement = ' `characters`.`DEF` ASC ';  break;
    case 'DEF-DESC': $orderStatement = ' `characters`.`DEF` DESC '; break;
  }

  // 現在ページのキャラクター一覧を取得
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
      "
        .($skill !== '' ? " , IFNULL(GROUP_CONCAT(DISTINCT `skills_master_data`.`name` SEPARATOR ' '), '') AS `skills` " : "").
      "
    FROM
      `characters`
    LEFT JOIN
      `characters_tags` ON `characters`.`ENo` = `characters_tags`.`ENo`
    "
      .($skill !== '' ? " LEFT JOIN `characters_skills` ON `characters_skills`.`ENo` = `characters`.`ENo` JOIN `skills_master_data` ON `skills_master_data`.`skill_id` = `characters_skills`.`skill` " : "").
    "
    WHERE
      `characters`.`administrator` = false AND
      `characters`.`deleted`       = false AND 
      ".$nameStatement." AND
      ".$modeStatement."
    GROUP BY
      `characters`.`ENo`,
      `characters`.`nickname`,
      `characters`.`summary`,
      `characters`.`ATK`,
      `characters`.`DEX`,
      `characters`.`MND`,
      `characters`.`AGI`,
      `characters`.`DEF`
    HAVING
      ".$tagStatement." AND
      ".$skillStatement."
    ORDER BY
      ".$orderStatement."
    LIMIT
      :offset, :number;
  ");

  $statement->bindValue(':offset', $page * $GAME_CONFIG['CHARACTER_SEARCH_ITEMS_PER_PAGE'], PDO::PARAM_INT);
  $statement->bindValue(':number', $GAME_CONFIG['CHARACTER_SEARCH_ITEMS_PER_PAGE'] + 1,     PDO::PARAM_INT);

  if ($name  !== '')        $statement->bindValue(':name',  '%'.$name.'%');
  if ($tag   !== '')        $statement->bindValue(':tag',   '%'.$tag.'%');
  if ($skill !== '')        $statement->bindValue(':skill', '%'.$skill.'%');
  if ($mode  !== 'default') $statement->bindValue(':ENo',    $_SESSION['ENo']);

  $result = $statement->execute();

  if (!$result) {
    // SQLの実行に失敗した場合は500(Internal Server Error)を返し処理を中断
    responseError(500);
  }

  $characters = $statement->fetchAll();

  // 1件余分に取得できていれば次のページありとして余分な1件を切り捨て
  if (count($characters) == $GAME_CONFIG['CHARACTER_SEARCH_ITEMS_PER_PAGE'] + 1) {
    $existsNext = true;
    array_pop($characters);
  } else {
  // 取得件数が足りなければ次のページなしとする
    $existsNext = false;
  }

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

.search th {
  padding: 0 10px 0 20px; 

}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>キャラクター検索</h1>

<form method="get">
  <h2>検索条件</h2>
  
  <table class="search">
    <tbody>
      <tr>
        <th>キャラクター名</th>
        <td><input class="search-input" type="text" name="name" value="<?=htmlspecialchars($name)?>"></td>
        <th>タグ</th>
        <td><input class="search-input" type="text" name="tag" value="<?=htmlspecialchars($tag)?>"></td>
      </tr>
      <tr>
        <th>スキル名</th>
        <td><input class="search-input" type="text" name="skill" value="<?=htmlspecialchars($skill)?>"></td>
        <th>並び順</th>
        <td>
          <select name="order">
            <option value="default"<?=  $order == 'default'  ? ' selected': ''?>>デフォルト</option>
            <option value="ENo-ASC"<?=  $order == 'ENo-ASC'  ? ' selected': ''?>>ENo昇順</option>
            <option value="ENo-DESC"<?= $order == 'ENo-DESC' ? ' selected': ''?>>ENo降順</option>
            <option value="ATK-ASC"<?=  $order == 'ATK-ASC'  ? ' selected': ''?>>ATK昇順</option>
            <option value="ATK-DESC"<?= $order == 'ATK-DESC' ? ' selected': ''?>>ATK降順</option>
            <option value="DEX-ASC"<?=  $order == 'DEX-ASC'  ? ' selected': ''?>>DEX昇順</option>
            <option value="DEX-DESC"<?= $order == 'DEX-DESC' ? ' selected': ''?>>DEX降順</option>
            <option value="MND-ASC"<?=  $order == 'MND-ASC'  ? ' selected': ''?>>MND昇順</option>
            <option value="MND-DESC"<?= $order == 'MND-DESC' ? ' selected': ''?>>MND降順</option>
            <option value="AGI-ASC"<?=  $order == 'AGI-ASC'  ? ' selected': ''?>>AGI昇順</option>
            <option value="AGI-DESC"<?= $order == 'AGI-DESC' ? ' selected': ''?>>AGI降順</option>
            <option value="DEF-ASC"<?=  $order == 'DEF-ASC'  ? ' selected': ''?>>DEF昇順</option>
            <option value="DEF-DESC"<?= $order == 'DEF-DESC' ? ' selected': ''?>>DEF降順</option>
          </select>
        </td>
      </tr>
      <tr>
        <th>絞り込み</th>
        <td>
          <select name="mode">
            <option value="default"<?= $mode == 'default' ? ' selected': ''?>>デフォルト</option>
            <option value="fav"<?=     $mode == 'fav'     ? ' selected': ''?>>お気に入りしている</option>
            <option value="faved"<?=   $mode == 'faved'   ? ' selected': ''?>>お気に入りされている</option>
          </select>
        </td>
      </tr>
    </tbody>
  </table>

  <div class="button-wrapper">
    <button class="button" type="submit">検索</button>
  </div>
</form>

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?name=<?=htmlspecialchars($name)?>&tag=<?=htmlspecialchars($tag)?>&skill=<?=htmlspecialchars($skill)?>&order=<?=htmlspecialchars($order)?>&mode=<?=htmlspecialchars($mode)?>&page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?name=<?=htmlspecialchars($name)?>&tag=<?=htmlspecialchars($tag)?>&skill=<?=htmlspecialchars($skill)?>&order=<?=htmlspecialchars($order)?>&mode=<?=htmlspecialchars($mode)?>&page=<?=$page+1-1?>">次のページ</a></div>
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

<section class="pagelinks next-prev-pagelinks">
  <div><a class="pagelink<?= 0 < $page   ? '' : ' next-prev-pagelinks-invalid'?>" href="?name=<?=htmlspecialchars($name)?>&tag=<?=htmlspecialchars($tag)?>&skill=<?=htmlspecialchars($skill)?>&order=<?=htmlspecialchars($order)?>&mode=<?=htmlspecialchars($mode)?>&page=<?=$page+1-1?>">前のページ</a></div>
  <span class="next-prev-pagelinks-page">ページ<?=$page+1?></span>
  <div><a class="pagelink<?= $existsNext ? '' : ' next-prev-pagelinks-invalid'?>" href="?name=<?=htmlspecialchars($name)?>&tag=<?=htmlspecialchars($tag)?>&skill=<?=htmlspecialchars($skill)?>&order=<?=htmlspecialchars($order)?>&mode=<?=htmlspecialchars($mode)?>&page=<?=$page+1-1?>">次のページ</a></div>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>
