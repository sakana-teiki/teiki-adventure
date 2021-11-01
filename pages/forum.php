<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  $PAGE_SETTING['TITLE'] = '掲示板';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

#board-table {
  margin: 10px 0 0 10px;
}

#board-table tr td {
  padding: 5px 10px;
}

#board-table a {
  text-decoration: none;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

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