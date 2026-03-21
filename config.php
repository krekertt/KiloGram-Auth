<?php
session_start();

define('DB_HOST', 'db.fr-pari1.bengt.wasmernet.com');
define('DB_PORT', '10272');
define('DB_NAME', 'sqliteo');
define('DB_USER', 'e980db847686800083ac5b13ee25');
define('DB_PASS', '069be980-db84-7779-8000-3395ab35ec5d);

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch(PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Функции остаются теми же
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $db;
    if (!isLoggedIn()) return null;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function updateLastSeen($user_id) {
    global $db;
    $stmt = $db->prepare("UPDATE users SET last_seen = NOW(), status = 'online' WHERE id = ?");
    $stmt->execute([$user_id]);
}

// Обновляем статус при каждом действии
if (isLoggedIn()) {
    updateLastSeen($_SESSION['user_id']);
}
?>
