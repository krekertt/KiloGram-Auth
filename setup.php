<?php
// setup.php - ЗАПУСТИТЕ ОДИН РАЗ ДЛЯ СОЗДАНИЯ БД
require_once 'config.php';

echo "<h2>Установка базы данных KiloGram</h2>";

// Читаем SQL файл
$sql = file_get_contents('database.sql');

// Выполняем SQL запросы
try {
    $db->exec($sql);
    echo "<p style='color:green'>✅ База данных успешно создана!</p>";
    
    // Создаем тестового пользователя с паролем "123456"
    $hashed_password = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        INSERT OR IGNORE INTO users (username, email, password, first_name, last_name, status) 
        VALUES (?, ?, ?, ?, ?, 'online')
    ");
    $stmt->execute(['test', 'test@test.com', $hashed_password, 'Тест', 'Тестовый']);
    
    echo "<p style='color:green'>✅ Тестовый пользователь создан (test/123456)</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Ошибка: " . $e->getMessage() . "</p>";
}

// Проверяем права на папки
$folders = ['db', 'avatars', 'uploads'];
foreach ($folders as $folder) {
    if (is_writable($folder)) {
        echo "<p style='color:green'>✅ Папка '$folder' доступна для записи</p>";
    } else {
        echo "<p style='color:red'>❌ Папка '$folder' НЕ доступна для записи</p>";
    }
}

echo "<hr>";
echo "<a href='login.php'>Перейти к входу</a>";
?>