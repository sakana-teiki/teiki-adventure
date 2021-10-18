<?php 
/*
  認証情報をチェックするミドルウェアです。
  GETリクエストの場合、ログインしていなければsignin.phpにリダイレクトします。
  POSTリクエストの場合、ログインチェックに加えCSRFトークンを検証しNGであれば403(Forbidden)を返します。
  PUT, DELETEリクエストに関しては必ず405(Method Not Allowed)を返します。
*/

  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // GETリクエストの場合、ログインしていなければsignin.phpにリダイレクトし以降の処理を行わない
    if (!$GAME_LOGGEDIN) {
      header('Location:'.$GAME_CONFIG['SIGNIN_URI'], true, 302);
      exit;
    }
  } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // POSTリクエストの場合、以下の条件のいずれかを満たせば403(Forbidden)を返し以降の処理を行わない
    if (
      !$GAME_LOGGEDIN              ||            // ログインしていない場合
      !isset($_POST['csrf_token']) ||            // 受け取ったデータにCSRFトークンが含まれていない場合
      $_POST['csrf_token'] != $_SESSION['token'] // セッションに記録されたCSRFトークンと受け取ったCSRFトークンが合致しない場合
    ) {
      http_response_code(403);
      exit;
    }
  } else {
    // GET, POSTリクエスト以外(PUT, DELETE)の場合は必ず405(Method Not Allowed)を返し以降の処理を行わない
    http_response_code(405);
    exit;
  }
  
?>