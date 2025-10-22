<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Безопасная и необязательная инициализация метрик БД
$total_users = 0;
$total_emails = 0;
$db_error = '';

if (class_exists('PDO')) {
    if (file_exists(__DIR__ . '/config.php') && file_exists(__DIR__ . '/db_connect.php')) {
        try {
            require __DIR__ . '/db_connect.php'; // создаёт $pdo
            if (isset($pdo)) {
                $stmt = $pdo->query("SELECT COUNT(*) AS total_users FROM mailbox");
                $total_users = (int)$stmt->fetchColumn();

                $stmt = $pdo->query("SELECT SUM(messages) AS total_emails FROM used_quota");
                $total_emails = (int)($stmt->fetchColumn() ?: 0);
            }
        } catch (Throwable $e) {
            $db_error = $e->getMessage();
        }
    } else {
        $db_error = 'Конфигурация БД отсутствует. Метрики почты скрыты.';
    }
} else {
    $db_error = 'Расширение PDO не установлено. Метрики почты скрыты.';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет администратора</title>
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
        .content {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .stat-card {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 10px;
            flex: 1 1 calc(50% - 20px);
            box-sizing: border-box;
            text-align: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .stat-card h3 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .stat-card p {
            margin: 10px 0 0;
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Личный кабинет администратора</h2>
        <div class="menu">
            <a href="admin_dashboard.php" class="menu-link">Главная</a>
            <a href="create_user.php" class="menu-link">Создать пользователя</a>
            <!-- <a href="create_ad_user.php" class="menu-link">Создать пользователя AD</a> -->
            <a href="ad_user_list.php" class="menu-link">Все пользователи AD</a>
            <a href="user_list.php" class="menu-link">Все пользователи</a>
            <a href="logout.php" class="menu-link">Выйти</a>
        </div>
        <div id="content" class="content">
            <div class="stats">
                <div class="stat-card">
                    <h3>Общее количество пользователей</h3>
                    <p><?php echo htmlspecialchars((string)$total_users); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Общее количество всех писем</h3>
                    <p><?php echo htmlspecialchars((string)$total_emails); ?></p>
                </div>
            </div>
            <?php if ($db_error): ?>
                <p style="text-align:center;color:#aa0000;margin-top:10px;">
                    <?php echo htmlspecialchars($db_error); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const links = document.querySelectorAll('.menu-link');
            links.forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    window.location.href = link.getAttribute('href');
                });
            });
        });
    </script>
</body>
</html>