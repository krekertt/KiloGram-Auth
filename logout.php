<?php
session_start();

// Обновляем статус перед выходом
if (isset($_SESSION['user_id'])) {
    require_once 'config.php';
    $stmt = $db->prepare("UPDATE users SET status = 'offline', last_seen = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Очищаем сессию
$_SESSION = array();
session_destroy();

// Удаляем cookie сессии
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header('Location: login.php');
exit;
?>