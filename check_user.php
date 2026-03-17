<?php
require_once 'config.php';

$phone = $_GET['phone'] ?? '+12128910259'; // Замени на свой номер

$stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
$stmt->execute([$phone]);
$user = $stmt->fetch();

echo "<pre>";
if ($user) {
    echo "Пользователь найден:\n";
    print_r($user);
    echo "\nTelegram Chat ID: " . ($user['telegram_chat_id'] ?? 'НЕ УСТАНОВЛЕН');
} else {
    echo "Пользователь с номером $phone не найден";
}
echo "</pre>";
?>