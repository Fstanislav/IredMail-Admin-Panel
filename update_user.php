<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db_connect.php';

$username = $_POST['username'];
$new_username = $_POST['username'];
$name = $_POST['name'];
$domain = $_POST['domain'];
$quota = $_POST['quota'];
$password = $_POST['password'];

// Обновление данных пользователя
$sql = "UPDATE mailbox SET username = :new_username, name = :name, domain = :domain, quota = :quota WHERE username = :username";
$stmt = $pdo->prepare($sql);
$stmt->execute(['new_username' => $new_username, 'name' => $name, 'domain' => $domain, 'quota' => $quota, 'username' => $username]);

// Обновление пароля, если он был указан
if (!empty($password)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE mailbox SET password = :password WHERE username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['password' => $hashed_password, 'username' => $username]);
}

echo "<script>alert('Изменения сохранены'); window.location.href='user_list.php';</script>";
?>