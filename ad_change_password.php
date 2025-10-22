<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/ad_ldap.php';

$dn = isset($_GET['dn']) ? (string)$_GET['dn'] : '';
$name = isset($_GET['name']) ? (string)$_GET['name'] : '';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dn = isset($_POST['dn']) ? (string)$_POST['dn'] : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $password2 = isset($_POST['password2']) ? (string)$_POST['password2'] : '';

    if ($dn === '') {
        $error = 'Не передан DN пользователя.';
    } elseif ($password === '' || $password2 === '') {
        $error = 'Введите новый пароль и его подтверждение.';
    } elseif ($password !== $password2) {
        $error = 'Пароли не совпадают.';
    } else {
        try {
            ad_change_user_password($dn, $password);
            $success = 'Пароль успешно изменён.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Смена пароля AD</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .hint { color:#666; font-size: 12px; margin-top:4px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Смена пароля в AD</h2>
    <div class="menu">
        <a href="ad_user_list.php" class="menu-link">К списку пользователей</a>
        <a href="admin_dashboard.php" class="menu-link">Главная</a>
        <a href="logout.php" class="menu-link">Выйти</a>
    </div>

    <?php if ($success): ?>
        <div class="notification" style="display:block;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="notification error" style="display:block;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="dn" value="<?php echo htmlspecialchars($dn); ?>">
        <div class="form-group">
            <label>Пользователь:</label>
            <input type="text" value="<?php echo htmlspecialchars($name ?: $dn); ?>" disabled>
        </div>
        <div class="form-group">
            <label for="password">Новый пароль:</label>
            <input type="password" id="password" name="password" required>
            <div class="hint">Изменение пароля требует защищённого соединения LDAPS; убедитесь, что порт 636 доступен.</div>
        </div>
        <div class="form-group">
            <label for="password2">Подтверждение пароля:</label>
            <input type="password" id="password2" name="password2" required>
        </div>
        <div class="form-group">
            <button type="submit">Сменить пароль</button>
        </div>
    </form>
</div>
</body>
</html>
