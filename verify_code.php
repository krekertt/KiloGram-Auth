<?php
require_once 'config.php';

$phone = $_POST['phone'] ?? '';
$code = $_POST['code'] ?? '';

// Здесь должна быть проверка кода через API
// Пока просто создаём сессию для теста

$stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
$stmt->execute([$phone]);
$user = $stmt->fetch();

if ($user) {
    $_SESSION['user_id'] = $user['id'];
    header('Location: index.php');
} else {
    header('Location: login.php?step=code&phone=' . urlencode($phone) . '&error=invalid_code');
}
exit;
?>