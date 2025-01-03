<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Получение данных из формы
    $email = $_POST['email'];
    $password = $_POST['password'];
    $name = $_POST['name'];
    $quota = $_POST['quota'];

    // Извлечение домена из email
    $domain = substr(strrchr($email, "@"), 1);

    // Хеширование пароля
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // SQL-запрос для вставки нового пользователя
    $sql = "INSERT INTO mailbox (username, password, name, domain, created, modified, active, quota) 
            VALUES (:email, :password, :name, :domain, NOW(), NOW(), 1, :quota)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'email' => $email,
        'password' => $hashed_password,
        'name' => $name,
        'domain' => $domain,
        'quota' => $quota,
    ]);

    echo "<script>alert('Пользователь успешно создан!'); window.location.href='admin_dashboard.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать пользователя</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h2>Создать пользователя</h2>
        <div class="menu">
            <a href="admin_dashboard.php" class="menu-link">Главная</a>
            <a href="create_user.php" class="menu-link">Создать пользователя</a>
            <a href="user_list.php" class="menu-link">Все пользователи</a>
            <a href="logout.php" class="menu-link">Выйти</a>
        </div>
        <form action="create_user.php" method="post">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="name">Имя:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="quota">Квота (в МБ):</label>
                <input type="number" id="quota" name="quota" required>
            </div>
            <div class="form-group">
                <button type="submit">Создать</button>
            </div>
        </form>
    </div>
</body>
</html>