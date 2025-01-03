<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$username = $_GET['username'];
$sql = "SELECT username, name, domain, quota FROM mailbox WHERE username = :username";
$stmt = $pdo->prepare($sql);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if (!$user) {
    echo "Пользователь не найден.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование пользователя</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h2>Редактирование пользователя</h2>
        <form action="update_user.php" method="post">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
            <div class="form-group">
                <label for="username">Email:</label>
                <input type="email" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="name">Имя:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="domain">Домен:</label>
                <input type="text" id="domain" name="domain" value="<?php echo htmlspecialchars($user['domain']); ?>" required>
            </div>
            <div class="form-group">
                <label for="quota">Квота (в МБ):</label>
                <input type="number" id="quota" name="quota" value="<?php echo htmlspecialchars($user['quota']); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Новый пароль (оставьте пустым, если не хотите менять):</label>
                <input type="password" id="password" name="password">
            </div>
            <div class="form-group">
                <button type="submit">Сохранить</button>
            </div>
        </form>
    </div>
</body>
</html>