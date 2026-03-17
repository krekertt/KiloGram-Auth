<?php
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$chat_id = $data['chat_id'] ?? '';
$phone = $data['phone'] ?? '';

if (!$chat_id || !$phone) {
    die(json_encode(['error' => 'missing_data']));
}

// Ищем пользователя по номеру
$stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
$stmt->execute([$phone]);
$user = $stmt->fetch();

if ($user) {
    // Обновляем chat_id
    $stmt = $db->prepare("UPDATE users SET telegram_chat_id = ? WHERE phone = ?");
    $stmt->execute([$chat_id, $phone]);
    echo json_encode(['success' => true, 'action' => 'updated']);
} else {
    // Создаём нового
    $username = 'user_' . rand(1000, 9999);
    $email = $username . '@temp.com';
    $password = password_hash('temp123', PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("INSERT INTO users (username, email, password, phone, telegram_chat_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $password, $phone, $chat_id]);
    echo json_encode(['success' => true, 'action' => 'created']);
}
?>