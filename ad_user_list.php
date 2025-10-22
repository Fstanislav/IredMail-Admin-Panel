<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/ad_ldap.php';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$error = '';
$users = [];
try {
    $users = ad_get_users($q);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пользователи Active Directory</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .actions-btn { padding: 6px 10px; background:#007bff; color:#fff; border-radius:4px; text-decoration:none; }
        .actions-btn:hover { background:#0069d9; }
        .search-bar { display:flex; gap:10px; align-items:center; }
        .search-bar input { flex:1; }
    </style>
</head>
<body>
<div class="container">
    <h2>Пользователи Active Directory</h2>
    <div class="menu">
        <a href="admin_dashboard.php" class="menu-link">Главная</a>
        <a href="ad_user_list.php" class="menu-link">Все пользователи AD</a>
        <a href="logout.php" class="menu-link">Выйти</a>
    </div>

    <?php if ($error): ?>
        <div class="notification error" style="display:block;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="get" class="search-bar">
        <input type="text" name="q" placeholder="Поиск: имя, логин, UPN, почта" value="<?php echo htmlspecialchars($q); ?>">
        <button type="submit">Искать</button>
    </form>

    <table class="user-list">
        <tr>
            <th>Отображаемое имя</th>
            <th>Логин (sAM)</th>
            <th>Email</th>
            <th>UPN</th>
            <th>DN</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo htmlspecialchars($u['displayName']); ?></td>
                <td><?php echo htmlspecialchars($u['sAMAccountName']); ?></td>
                <td><?php echo htmlspecialchars($u['mail']); ?></td>
                <td><?php echo htmlspecialchars($u['userPrincipalName']); ?></td>
                <td style="font-size:12px; color:#555; "><?php echo htmlspecialchars($u['distinguishedName']); ?></td>
                <td>
                    <a class="actions-btn" href="ad_change_password.php?dn=<?php echo urlencode($u['distinguishedName']); ?>&name=<?php echo urlencode($u['displayName']); ?>">Сменить пароль</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
