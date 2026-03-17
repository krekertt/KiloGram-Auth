<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_chats':
        getChats();
        break;
    case 'get_messages':
        getMessages();
        break;
    case 'send_message':
        sendMessage();
        break;
    case 'search_users':
        searchUsers();
        break;
    case 'create_chat':
        createChat();
        break;
    case 'delete_chat':
        deleteChat();
        break;
    default:
        echo json_encode(['error' => 'Неизвестное действие']);
}

function getChats() {
    global $db;
    $user_id = $_SESSION['user_id'];
    
    try {
        $stmt = $db->prepare("
            SELECT 
                c.id,
                c.type,
                c.name,
                c.avatar,
                CASE 
                    WHEN c.type = 'private' THEN (
                        SELECT username FROM users 
                        WHERE id IN (
                            SELECT user_id FROM chat_participants 
                            WHERE chat_id = c.id AND user_id != ?
                        )
                    )
                    ELSE c.name
                END as display_name,
                (
                    SELECT content FROM messages 
                    WHERE chat_id = c.id 
                    ORDER BY created_at DESC LIMIT 1
                ) as last_message,
                (
                    SELECT created_at FROM messages 
                    WHERE chat_id = c.id 
                    ORDER BY created_at DESC LIMIT 1
                ) as last_message_time,
                (
                    SELECT COUNT(*) FROM messages m
                    LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?
                    WHERE m.chat_id = c.id 
                    AND m.user_id != ?
                    AND (ms.status IS NULL OR ms.status != 'read')
                ) as unread_count
            FROM chats c
            JOIN chat_participants cp ON c.id = cp.chat_id
            WHERE cp.user_id = ?
            ORDER BY last_message_time DESC
        ");
        
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
        $chats = $stmt->fetchAll();
        
        echo json_encode(['chats' => $chats]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
}

function getMessages() {
    global $db;
    $user_id = $_SESSION['user_id'];
    $chat_id = $_GET['chat_id'] ?? 0;
    $limit = $_GET['limit'] ?? 50;
    
    // Проверяем доступ
    $stmt = $db->prepare("SELECT 1 FROM chat_participants WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chat_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Нет доступа']);
        return;
    }
    
    try {
        // Загружаем ПОСЛЕДНИЕ сообщения и переворачиваем для хронологии
        $stmt = $db->prepare("
            SELECT 
                m.*,
                u.username,
                u.first_name,
                u.last_name,
                u.avatar
            FROM messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.chat_id = ?
            ORDER BY m.id DESC
            LIMIT ?
        ");
        
        $stmt->execute([$chat_id, $limit]);
        $messages = $stmt->fetchAll();
        
        // Переворачиваем для хронологического порядка (старые → новые)
        $messages = array_reverse($messages);
        
        echo json_encode(['messages' => $messages]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()]);
    }
}

function sendMessage() {
    global $db;
    $user_id = $_SESSION['user_id'];
    
    if (isset($_FILES['file'])) {
        handleFileUpload($user_id);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['error' => 'Неверный формат']);
        return;
    }
    
    $chat_id = $input['chat_id'] ?? 0;
    $content = trim($input['content'] ?? '');
    
    if (empty($content)) {
        echo json_encode(['error' => 'Пустое сообщение']);
        return;
    }
    
    $stmt = $db->prepare("SELECT 1 FROM chat_participants WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chat_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Нет доступа']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            INSERT INTO messages (chat_id, user_id, content, message_type, created_at)
            VALUES (?, ?, ?, 'text', datetime('now'))
        ");
        $stmt->execute([$chat_id, $user_id, $content]);
        $msg_id = $db->lastInsertId();
        
        $stmt = $db->prepare("
            INSERT INTO message_status (message_id, user_id, status)
            SELECT ?, user_id, 'sent' FROM chat_participants WHERE chat_id = ?
        ");
        $stmt->execute([$msg_id, $chat_id]);
        
        $db->commit();
        
        $stmt = $db->prepare("
            SELECT m.*, u.username, u.first_name, u.last_name, u.avatar
            FROM messages m JOIN users u ON m.user_id = u.id WHERE m.id = ?
        ");
        $stmt->execute([$msg_id]);
        
        echo json_encode(['success' => true, 'message' => $stmt->fetch()]);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()]);
    }
}

function handleFileUpload($user_id) {
    global $db;
    
    $chat_id = $_POST['chat_id'] ?? 0;
    $file_type = $_POST['file_type'] ?? 'image';
    $text = $_POST['text'] ?? '';
    
    $file = $_FILES['file'] ?? null;
    if (!$file) {
        echo json_encode(['error' => 'Файл не загружен']);
        return;
    }
    
    $upload_dir = 'uploads/' . date('Y/m/d/');
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $filename = time() . '_' . $file['name'];
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['error' => 'Ошибка сохранения']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            INSERT INTO messages 
            (chat_id, user_id, message_type, content, file_path, file_name, file_size, mime_type, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([
            $chat_id, $user_id, $file_type, $text,
            $filepath, $file['name'], $file['size'], $file['type']
        ]);
        
        $msg_id = $db->lastInsertId();
        
        $stmt = $db->prepare("
            INSERT INTO message_status (message_id, user_id, status)
            SELECT ?, user_id, 'sent' FROM chat_participants WHERE chat_id = ?
        ");
        $stmt->execute([$msg_id, $chat_id]);
        
        $db->commit();
        
        $stmt = $db->prepare("
            SELECT m.*, u.username, u.first_name, u.last_name, u.avatar
            FROM messages m JOIN users u ON m.user_id = u.id WHERE m.id = ?
        ");
        $stmt->execute([$msg_id]);
        
        echo json_encode(['success' => true, 'message' => $stmt->fetch()]);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()]);
    }
}

function searchUsers() {
    global $db;
    $user_id = $_SESSION['user_id'];
    $query = $_GET['q'] ?? '';
    
    if (strlen($query) < 2) {
        echo json_encode(['users' => []]);
        return;
    }
    
    try {
        // Простой запрос, который точно работал в тесте
        $stmt = $db->prepare("
            SELECT id, username, first_name, last_name, avatar, status
            FROM users
            WHERE username LIKE ? OR first_name LIKE ? OR last_name LIKE ?
        ");
        
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $users = $stmt->fetchAll();
        
        // Временно отправляем ВСЕХ, даже текущего
        echo json_encode(['users' => $users]);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()]);
    }
}

function createChat() {
    global $db;
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_ids = $data['user_ids'] ?? [];
    if (empty($user_ids)) {
        echo json_encode(['error' => 'Нет пользователей']);
        return;
    }
    
    if (count($user_ids) === 1) {
        $other = $user_ids[0];
        $stmt = $db->prepare("
            SELECT c.id FROM chats c
            JOIN chat_participants cp1 ON c.id = cp1.chat_id
            JOIN chat_participants cp2 ON c.id = cp2.chat_id
            WHERE c.type = 'private' AND cp1.user_id = ? AND cp2.user_id = ?
            AND (SELECT COUNT(*) FROM chat_participants WHERE chat_id = c.id) = 2
        ");
        $stmt->execute([$user_id, $other]);
        if ($existing = $stmt->fetch()) {
            echo json_encode(['success' => true, 'chat_id' => $existing['id']]);
            return;
        }
    }
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT INTO chats (type, created_by, created_at) VALUES ('private', ?, datetime('now'))");
        $stmt->execute([$user_id]);
        $chat_id = $db->lastInsertId();
        
        $stmt = $db->prepare("INSERT INTO chat_participants (chat_id, user_id, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$chat_id, $user_id]);
        
        $stmt = $db->prepare("INSERT INTO chat_participants (chat_id, user_id, role) VALUES (?, ?, 'member')");
        foreach ($user_ids as $uid) {
            if ($uid != $user_id) $stmt->execute([$chat_id, $uid]);
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'chat_id' => $chat_id]);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()]);
    }
}

function deleteChat() {
    global $db;
    $user_id = $_SESSION['user_id'];
    
    // Получаем данные из POST
    $input = json_decode(file_get_contents('php://input'), true);
    $chat_id = $input['chat_id'] ?? 0;
    
    error_log("Удаление чата ID: $chat_id для пользователя $user_id");
    
    if (!$chat_id) {
        echo json_encode(['error' => 'Не указан чат']);
        return;
    }
    
    try {
        // Удаляем только для текущего пользователя
        $stmt = $db->prepare("DELETE FROM chat_participants WHERE chat_id = ? AND user_id = ?");
        $stmt->execute([$chat_id, $user_id]);
        
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        error_log("Ошибка удаления чата: " . $e->getMessage());
        echo json_encode(['error' => 'Ошибка: ' . $e->getMessage()]);
    }
}

?>