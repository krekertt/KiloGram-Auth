<?php
require_once 'config.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_FILES['avatar'])) {
    echo json_encode(['error' => 'Файл не загружен']);
    exit;
}

$file = $_FILES['avatar'];

// Проверка типа файла
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed)) {
    echo json_encode(['error' => 'Неподдерживаемый формат. Разрешены: JPG, PNG, GIF, WEBP']);
    exit;
}

// Проверка размера (макс 5 МБ)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'Файл слишком большой. Максимум 5 МБ']);
    exit;
}

// Создаём папку если нет
if (!file_exists('avatars')) {
    mkdir('avatars', 0777);
}

// Генерируем имя файла
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
$filepath = 'avatars/' . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Удаляем старый аватар если не дефолтный
    $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $old = $stmt->fetch()['avatar'];
    
    if ($old && $old !== 'default_avatar.png' && file_exists('avatars/' . $old)) {
        unlink('avatars/' . $old);
    }
    
    // Обновляем в БД
    $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$filename, $user_id]);
    
    echo json_encode(['success' => true, 'avatar' => $filename]);
} else {
    echo json_encode(['error' => 'Ошибка при сохранении файла']);
}
?>