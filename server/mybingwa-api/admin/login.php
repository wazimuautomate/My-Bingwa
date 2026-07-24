<?php
require __DIR__ . '/lib_admin.php';

// Already signed in → straight to the dashboard.
if (!empty($_SESSION['admin_ok'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $config = admin_config();
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    $okUser = hash_equals((string) ($config['admin_user'] ?? ''), $user);
    $okPass = hash_equals((string) ($config['admin_pass'] ?? ''), $pass);
    if ($okUser && $okPass && $user !== '') {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Wrong username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Bingwa Admin — Sign in</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-wrap">
    <div class="card">
        <div class="mark">My <b>Bingwa</b></div>
        <p class="sub">Admin sign in</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label for="user">Username</label>
            <input id="user" name="user" autocomplete="username" autofocus>
            <label for="pass">Password</label>
            <input id="pass" name="pass" type="password" autocomplete="current-password">
            <div style="height:16px"></div>
            <button class="btn" style="width:100%">Sign in</button>
            <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
        </form>
    </div>
</div>
</body>
</html>
