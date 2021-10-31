<?php

// 入力内容を検証するための関数群です。

/**
* $targetの内容を検証します。
* 
* @param string $target 検証対象となる文字列です。
* @param array $validators 検証内容を示す文字列の配列です。
* @param int $maxLength 文字列の最大の長さを指定します。未設定あるいは-1の場合長さに制限は設けません。
* @return bool 検証をパスした場合はtrue、NGの場合はfalse。
*/

function validateString($target, $validators, $maxLength = -1) {
  if (!isset($target)) {
    return false;
  }

  if ($maxLength !== -1 && $maxLength < mb_strlen($target)) {
    return false;
  }

  foreach ($validators as $validator) {
    switch ($validator) {
      case 'non-empty': // 検査対象は空文字列であってはならない
        if (mb_strlen($target) == 0) {
          return false;
        }
        break;
      case 'single-line': // 検査対象は空文字列か1行でなければならない
        if (strpos($target, "\r") != false || strpos($target, "\n") != false) {
          return false;
        }
        break;
      case 'integer': // 検査対象は空文字列か整数でなければならない
        if ($target !== '' && !preg_match('/^(0|(\+|\-)?[1-9][0-9]*)$/', $target)) {
          return false;
        }
        break;
      case 'natural-number': // 検査対象は空文字列か自然数でなければならない
        if ($target !== '' && !preg_match('/^[1-9][0-9]*$/', $target)) {
          return false;
        }
        break;
      case 'boolean': // 検査対象は空文字列か真偽値でなければならない
        if ($target !== '' && $target !== 'true' && $target !== 'false') {
          return false;
        }
        break;
      case 'disallow-special-chars': // 検査対象は特殊文字を含んでいてはならない
        if (
          mb_ereg('/\p{C}/u', $target)       != false || // Unicode C Category（制御文字）
          preg_match('/^\xC2\xA0/', $target) != false    // ゼロ幅スペース
        ) {
          return false;
        }
        break;
      case 'disallow-space-only': // 検査対象は空文字列か空白以外の文字を含んでいる文字列でなければならない
        if ($target !== '' && preg_match('/^[\x{0009}-\x{000d}\x{001c}-\x{0020}\x{11a3}-\x{11a7}\x{1680}\x{180e}\x{2000}-\x{200f}\x{202f}\x{205f}\x{2060}\x{3000}\x{3164}\x{feff}\x{034f}\x{2028}\x{2029}\x{202a}-\x{202e}\x{2061}-\x{2063}\x{feff}]*$/u', $target) === 1) {
          return false;
        }
        break;
      default:
        // どれでもない検査条件があった場合エラーをスロー
        throw new Exception('検査条件に不明な条件"'.$validator.'"が含まれています。');
        break;
    }
  }

  return true;
}

/**
* $_GET[$target]の内容を検証します。
* 
* @param string $target 検証対象となる文字列です。
* @param array $validators 検証内容を示す文字列の配列です。
* @param int $maxLength 文字列の最大の長さを指定します。未設定あるいは-1の場合長さに制限は設けません。
* @return bool 検証をパスした場合はtrue、NGの場合はfalse。
*/

function validateGET($target, $validators, $maxLength = -1) {
  if (!isset($_GET[$target])) {
    return false;
  }

  return validateString($_GET[$target], $validators, $maxLength = -1);
}

/**
* $_POST[$target]の内容を検証します。
* 
* @param string $target 検証対象となる文字列です。
* @param array $validators 検証内容を示す文字列の配列です。
* @param int $maxLength 文字列の最大の長さを指定します。未設定あるいは-1の場合長さに制限は設けません。
* @return bool 検証をパスした場合はtrue、NGの場合はfalse。
*/

function validatePOST($target, $validators, $maxLength = -1) {
  if (!isset($_POST[$target])) {
    return false;
  }

  return validateString($_POST[$target], $validators, $maxLength = -1);
}

?>