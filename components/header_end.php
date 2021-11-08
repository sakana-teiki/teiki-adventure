</head>
<body>
<?php if ($GAME_LOGGEDIN_AS_ADMINISTRATOR) { ?>
  <div style="position:fixed; right:10px; top:10px; pointer-events:none; background: #c2193e; padding: 10px 20px; color: white; font-weight: bold; border-radius: 8px;">管理者モード<?= $GAME_MAINTENANCE ? ' / メンテナンス中' : '' ?></div>
<?php } ?>
<header>
  <section id="title">
    <a id="title-logo" href="<?php if ($GAME_LOGGEDIN) { echo $GAME_CONFIG['HOME_URI']; } else { echo $GAME_CONFIG['TOP_URI']; }?>">Teiki Adventure</a>
  </section>
</header>
<section id="columns">
<nav id="sub-column">
<?php include GETENV('GAME_ROOT').'/components/sidemenu.php'; ?>
</nav>
<main id="main-column">