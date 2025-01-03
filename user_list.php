<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

// Получение списка пользователей с дополнительной информацией о количестве писем
$sql = "
    SELECT 
        mailbox.username, 
        mailbox.name, 
        mailbox.domain, 
        mailbox.quota, 
        COALESCE(used_quota.messages, 0) AS total_emails
    FROM 
        mailbox
    LEFT JOIN 
        used_quota ON mailbox.username = used_quota.username
";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Все пользователи</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .menu {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .menu a {
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
            text-align: center;
            flex: 1 1 calc(33.333% - 10px);
            box-sizing: border-box;
        }
        .menu a:hover {
            background-color: #218838;
        }
        .user-list {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .user-list th, .user-list td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .user-list th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .user-list tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .user-list tr:hover {
            background-color: #f1f1f1;
        }
        .user-list td a {
            color: #28a745;
            text-decoration: none;
        }
        .user-list td a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Все пользователи</h2>
        <div class="menu">
            <a href="admin_dashboard.php" class="menu-link">Главная</a>
            <a href="create_user.php" class="menu-link">Создать пользователя</a>
            <a href="user_list.php" class="menu-link">Все пользователи</a>
            <a href="logout.php" class="menu-link">Выйти</a>
        </div>
        <table class="user-list">
            <tr><th>Email</th><th>Имя</th><th>Домен</th><th>Квота (МБ)</th><th>Количество писем</th><th>Действия</th></tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['domain']); ?></td>
                    <td><?php echo htmlspecialchars($user['quota']); ?></td>
                    <td><?php echo htmlspecialchars($user['total_emails']); ?></td>
                    <td><a href="edit_user.php?username=<?php echo urlencode($user['username']); ?>">Редактировать</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>