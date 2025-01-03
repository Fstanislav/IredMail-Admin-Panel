<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_user = $_POST['admin_user'];
    $admin_pass = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];

    $config_content = "<?php\n";
    $config_content .= "\$admin_user = '$admin_user';\n";
    $config_content .= "\$admin_pass = '$admin_pass';\n";
    $config_content .= "\$db_host = '$db_host';\n";
    $config_content .= "\$db_name = '$db_name';\n";
    $config_content .= "\$db_user = '$db_user';\n";
    $config_content .= "\$db_pass = '$db_pass';\n";
    $config_content .= "\$charset = 'utf8mb4';\n";
    $config_content .= "\$dsn = \"mysql:host=\$db_host;dbname=\$db_name;charset=\$charset\";\n";
    $config_content .= "\$options = [\n";
    $config_content .= "    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
    $config_content .= "    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
    $config_content .= "    PDO::ATTR_EMULATE_PREPARES => false,\n";
    $config_content .= "];\n";
    $config_content .= "try {\n";
    $config_content .= "    \$pdo = new PDO(\$dsn, \$db_user, \$db_pass, \$options);\n";
    $config_content .= "} catch (PDOException \$e) {\n";
    $config_content .= "    throw new PDOException(\$e->getMessage(), (int)\$e->getCode());\n";
    $config_content .= "}\n";
    $config_content .= "?>";

    file_put_contents('config.php', $config_content);

    echo "<script>alert('Настройки сохранены.'); window.location.href='login.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройка проекта</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h2>Настройка проекта</h2>
        <form action="setup.php" method="post">
            <div class="form-group">
                <label for="admin_user">Имя администратора:</label>
                <input type="text" id="admin_user" name="admin_user" required>
            </div>
            <div class="form-group">
                <label for="admin_pass">Пароль администратора:</label>
                <input type="password" id="admin_pass" name="admin_pass" required>
            </div>
            <div class="form-group">
                <label for="db_host">Хост базы данных:</label>
                <input type="text" id="db_host" name="db_host" required>
            </div>
            <div class="form-group">
                <label for="db_name">Имя базы данных:</label>
                <input type="text" id="db_name" name="db_name" required>
            </div>
            <div class="form-group">
                <label for="db_user">Пользователь базы данных:</label>
                <input type="text" id="db_user" name="db_user" required>
            </div>
            <div class="form-group">
                <label for="db_pass">Пароль базы данных:</label>
                <input type="password" id="db_pass" name="db_pass" required>
            </div>
            <div class="form-group">
                <button type="submit">Сохранить настройки</button>
            </div>
        </form>
    </div>
</body>
</html>