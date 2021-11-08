<?php 
/*
  管理者としての認証情報をチェックするミドルウェアです。
  GETリクエストの場合、管理者としてログインしていなければ404(Not Found)を返します。
  POSTリクエストの場合、ログインチェックに加えCSRFトークンを検証しNGであれば404(Not Found)を返します。
  PUT, DELETEリクエストに関しては必ず404(Not Found)を返します。
*/

  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // GETリクエストの場合、管理者としてログインしていなければ404(Not Found)を返し以降の処理を行わない
    if (!$GAME_LOGGEDIN_AS_ADMINISTRATOR) {
      responseError(404);
    }
  } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // POSTリクエストの場合、以下の条件のいずれかを満たせば404(Not Found)を返し以降の処理を行わない
    if (
      !$GAME_LOGGEDIN_AS_ADMINISTRATOR ||        // 管理者としてログインしていない場合
      !isset($_POST['csrf_token'])     ||        // 受け取ったデータにCSRFトークンが含まれていない場合
      $_POST['csrf_token'] != $_SESSION['token'] // セッションに記録されたCSRFトークンと受け取ったCSRFトークンが合致しない場合
    ) {
      responseError(404);
    }
  } else {
    // GET, POSTリクエスト以外(PUT, DELETE)の場合は必ず404(Not Found)を返し以降の処理を行わない
    responseError(404);
  }

?>