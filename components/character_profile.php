<?php
/*
  プロフィールを表示するコンポーネントです。
  このコンポーネントは以下の値を受け取ります。

  $COMPONENT_CHARACTER_PROFILE['type']:string
  キャラクタープロフィールの表示タイプを'home'、'profile'のいずれかで指定します。

  $COMPONENT_CHARACTER_PROFILE['profile_images']:array
  プロフィール画像の配列を指定します。

  $COMPONENT_CHARACTER_PROFILE['AP']:int
  所持APを指定します。表示タイプが'home'のときのみ表示されます。

  $COMPONENT_CHARACTER_PROFILE['ATK']:int
  $COMPONENT_CHARACTER_PROFILE['DEX']:int
  $COMPONENT_CHARACTER_PROFILE['MND']:int
  $COMPONENT_CHARACTER_PROFILE['AGI']:int
  $COMPONENT_CHARACTER_PROFILE['DEF']:int
  各ステータスの値を指定します。

  $COMPONENT_CHARACTER_PROFILE['tags']:array
  タグの配列を指定します。

  $COMPONENT_CHARACTER_PROFILE['profile']:string
  プロフィール文を指定します。

  $COMPONENT_CHARACTER_PROFILE['icons']:array
  アイコンの配列を指定します。utils/parser.phpのparseIconsResultの結果の形式で受け取ります。
*/

  require_once GETENV('GAME_ROOT').'/utils/decoration.php';

?>

<section class="profile-main">
  <section class="profile-image-wrapper">
  <?php if (count($COMPONENT_CHARACTER_PROFILE['profile_images']) == 0) { ?>
    <div class="profile-image"></div>
  <?php } else { ?>
    <img class="profile-image" src="<?=htmlspecialchars($COMPONENT_CHARACTER_PROFILE['profile_images'][array_rand($COMPONENT_CHARACTER_PROFILE['profile_images'])])?>">
  <?php } ?>
  </section>

  <section class="profile-summary">
    <?php if ($COMPONENT_CHARACTER_PROFILE['type'] === 'home') { ?>
    <section class="profile-ap-wrapper">
      <div class="profile-ap">AP</div>
      <div class="profile-ap-value"><?=$COMPONENT_CHARACTER_PROFILE['AP']?></div>
    </section>
    <?php } ?>

    <table class="profile-statuses">
      <tbody>
        <tr>
          <td class="profile-status-name">ATK</td>
          <td class="profile-status-value"><?=$COMPONENT_CHARACTER_PROFILE['ATK']?></td>
          <td class="profile-status-name">DEX</td>
          <td class="profile-status-value"><?=$COMPONENT_CHARACTER_PROFILE['DEX']?></td>
          <td class="profile-status-name">MND</td>
          <td class="profile-status-value"><?=$COMPONENT_CHARACTER_PROFILE['MND']?></td>
        </tr>
        <tr>
          <td class="profile-status-name">AGI</td>
          <td class="profile-status-value"><?=$COMPONENT_CHARACTER_PROFILE['AGI']?></td>
          <td class="profile-status-name">DEF</td>
          <td class="profile-status-value"><?=$COMPONENT_CHARACTER_PROFILE['DEF']?></td>
        </tr>
      </tbody>
    </table>

    <section class="profile-skills">
      <section class="profile-skill">
        <div class="profile-skill-prop">
          <div class="profile-skill-name">スキル1</div>
          <div class="profile-skill-cond">スキル発動条件</div>
        </div>
        <div class="profile-skill-effect">スキル効果</div>
      </section>
      <section class="profile-skill">
        <div class="profile-skill-prop">
          <div class="profile-skill-name">スキル2</div>
          <div class="profile-skill-cond">スキル発動条件</div>
        </div>
        <div class="profile-skill-effect">スキル効果</div>
      </section>
      <section class="profile-skill">
        <div class="profile-skill-prop">
          <div class="profile-skill-name">スキル3</div>
          <div class="profile-skill-cond">スキル発動条件</div>
        </div>
        <div class="profile-skill-effect">スキル効果</div>
      </section>
      <section class="profile-skill">
        <div class="profile-skill-prop">
          <div class="profile-skill-name">スキル4</div>
          <div class="profile-skill-cond">スキル発動条件</div>
        </div>
        <div class="profile-skill-effect">スキル効果</div>
      </section>
      <section class="profile-skill">
        <div class="profile-skill-prop">
          <div class="profile-skill-name">スキル5</div>
          <div class="profile-skill-cond">スキル発動条件</div>
        </div>
        <div class="profile-skill-effect">スキル効果</div>
      </section>
    </section>
  </section>
</section>

<section class="profile-tags">
  <h2>タグ</h2>
  <?php foreach ($COMPONENT_CHARACTER_PROFILE['tags'] as $tag) { ?>
  <span class="profile-tag"><?=htmlspecialchars($tag)?></span>
  <?php } ?>
</section>

<section>
  <h2>プロフィール</h2>
  <div class="profile-text">
    <?=profileDecoration($COMPONENT_CHARACTER_PROFILE['profile'])?>
  </div>
</section>

<section>
  <h2>アイコン</h2>
  <div class="profile-character-icons-wrapper">
  <?php
    for ($i = 0; $i < $GAME_CONFIG['ICONS_MAX']; $i++) {
  ?>
    <div class="profile-character-icon-wrapper">
  <?php
    $COMPONENT_ICON['src']   = isset($COMPONENT_CHARACTER_PROFILE['icons'][$i]) ? $COMPONENT_CHARACTER_PROFILE['icons'][$i]['url']  : '';
    $COMPONENT_ICON['title'] = isset($COMPONENT_CHARACTER_PROFILE['icons'][$i]) ? $COMPONENT_CHARACTER_PROFILE['icons'][$i]['name'] : '';
    include GETENV('GAME_ROOT').'/components/icon.php';
  ?>
    </div>
  <?php
    }
  ?>
  </div>
</section>