<?php
  if (isset($GAME_CONFIG['ENVIRONMENT']) && $GAME_CONFIG['ENVIRONMENT'] = 'development') {
    $backTraces = debug_backtrace();
    
    $cnt = count($backTraces);
    for ($i = 0; $i < $cnt; $i++) {
      echo ('#'.$i.' file:"'.$backTraces[$i]['file'].'"'.' line:'.$backTraces[$i]['line'].'<br>');
    }
  }
?>
400 Bad Request