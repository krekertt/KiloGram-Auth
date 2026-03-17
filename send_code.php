<?php
require_once 'config.php';

$phone = $_POST['phone'] ?? $_GET['phone'] ?? '';
$phone = preg_replace('/\s+/', '', $phone);
if (!str_starts_with($phone, '+')) {
    $phone = '+' . $phone;
}

// Ищем пользователя
$stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
$stmt->execute([$phone]);
$user = $stmt->fetch();

if (!$user) {
    // Создаём нового
    $username = 'user_' . rand(1000, 9999);
    $email = $username . '@temp.com';
    $password = password_hash('temp123', PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("INSERT INTO users (username, email, password, phone) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $password, $phone]);
}

// Перенаправляем на страницу ввода кода
header("Location: login.php?step=code&phone=" . urlencode($phone));
exit;
?>