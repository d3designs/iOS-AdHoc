<?php

// Initialize App
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/load_apps.php';
require_once __DIR__ . '/../src/load_passwords.php';

$login     = false;
$passwords = load_passwords();

if (!empty($_POST['app-login'])) {
    // Validate form login
    if (array_key_exists($_POST['app-login'], $passwords)) {
        $login = $_POST['app-login'];
    } elseif (!empty($_COOKIE['app-login'])) {
        // Delete any cookies on incorrect login
        setcookie('app-login', false, time()-3600, '', '', true, true);
    }
} elseif (!empty($_COOKIE['app-login'])) {
    // Check if cookie hash is valid
    $login = array_search($_COOKIE['app-login'], $passwords);
}

// Valid Login
if ($login !== false) {
    $apps  = load_apps("apps/$login");
    // Save login for one week
    setcookie('app-login', $passwords[$login], strtotime('+1 month'), '', '', true, true);
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>App Updates</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="apple-mobile-web-app-title" content="App Updates">
    <link rel="apple-touch-icon" href="img/apple-touch-icon.png" sizes="180x180">
    <link rel="icon" type="image/png" href="img/favicon-32x32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="img/favicon-16x16.png" sizes="16x16">

    <link rel="stylesheet" href="css/main.css">
</head>
<body>
<h1>App Updates</h1>

<?php if ($login !== false) : ?>

    <?php if (empty($apps)) : ?>
    <div class="app">
    <h3>There are no apps available at this time.</h3>
    </div>
    <?php endif; ?>

    <?php foreach ($apps as $app) : ?>
    <div class="app">

    <a href="itms-services://?action=download-manifest&url=<?= $app['assets']['manifest'] ?>">
      <img src="<?= $app['assets']['display-image'] ?>" alt="App Icon" width="57" height="57">
    </a>

    <h2><?= $app['metadata']['title'] ?></h2>

    <a class="download" href="itms-services://?action=download-manifest&url=<?= $app['assets']['manifest'] ?>">
      Install App
    </a>

    <ul>
        <li><b>Version</b> <?= $app['metadata']['bundle-version'] ?></li>
        <li><b>Updated</b> <?= $app['metadata']['updated'] ?></li>
        <li><b>Size</b>    <?= $app['metadata']['size'] ?></li>
    </ul>

    </div>
    <?php endforeach; ?>

    <div class="support">
    <h2>Support</h2>
    <p>All devices must be approved before the app will install. Please have your
    <a href="http://whatsmyudid.com">UDID Number</a> ready when
    requesting a new device be approved.
    </p>
    </div>

<?php else : ?>

    <div class="login">
    <h2>Account Login</h2>
    <form action="" method="post">
      <input type="password" name="app-login">
      <button type="submit">Login</button>
    </form>
    </div>

<?php endif; ?>
</body>
</html>