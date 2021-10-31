<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  $PAGE_SETTING['TITLE'] = '掲示板';

  require GETENV('GAME_ROOT').'/components/header.php';
?>

<h1>掲示板</h1>

<table id="board-table">
  <tbody>
    <tr>
      <th><a href="<?=$GAME_CONFIG['URI']?>board?board=community">交流掲示板</a></th>
      <td>交流等を行うための掲示板です。</td>
    </tr>
    <tr>
      <th><a href="<?=$GAME_CONFIG['URI']?>board?board=bug">不具合掲示板</a></th>
      <td>不具合等を報告するための掲示板です。</td>
    </tr>
  </tbody>
</table>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>