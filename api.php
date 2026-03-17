<?php
require_once 'config.php';

header('Content-Type: application/json');

// Токен твоего бота
define('BOT_TOKEN', '8081566708:AAHm4ppfiDQMVT_GCsTFmXXe-Z56UWae6AM');

// Действия, которые не требуют авторизации
$public_actions = ['register_phone', 'send_code', 'verify_code', 'get_user_by_phone'];

$action = $_GET['action'] ?? '';

// Проверка авторизации для защищённых действий
if (!in_array($action, $public_actions) && !isLoggedIn()) {
    echo json_encode(['error' => 'not_authorized']);
    exit;
}

switch ($action) {
    
    // ===== СУЩЕСТВУЮЩИЙ ЭКШЕН =====
    case 'get_current_user':
        echo json_encode(['user' => getCurrentUser()]);
        break;
    
    // ===== РЕГИСТРАЦИЯ НОМЕРА ОТ БОТА =====
    case 'register_phone':
        $data = json_decode(file_get_contents('php://input'), true);
        $chat_id = $data['chat_id'] ?? '';
        $phone = $data['phone'] ?? '';
        $type = $data['type'] ?? '';
        
        if (!$chat_id || !$phone) {
            echo json_encode(['error' => 'missing_data']);
            exit;
        }
        
        try {
            // Проверяем, есть ли пользователь с таким chat_id
            $stmt = $db->prepare("SELECT id FROM users WHERE telegram_chat_id = ?");
            $stmt->execute([$chat_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Обновляем номер существующего пользователя
                $stmt = $db->prepare("UPDATE users SET phone = ? WHERE id = ?");
                $stmt->execute([$phone, $user['id']]);
                $user_id = $user['id'];
                
                // Логируем
                error_log("Номер $phone обновлён для пользователя $user_id (telegram: $chat_id)");
            } else {
                // Создаём нового пользователя
                $username = 'user_' . substr(md5($chat_id), 0, 8);
                $stmt = $db->prepare("INSERT INTO users (username, phone, telegram_chat_id) VALUES (?, ?, ?)");
                $stmt->execute([$username, $phone, $chat_id]);
                $user_id = $db->lastInsertId();
                
                error_log("Создан новый пользователь $user_id с номером $phone (telegram: $chat_id)");
            }
            
            echo json_encode([
                'success' => true, 
                'phone' => $phone, 
                'user_id' => $user_id
            ]);
            
        } catch (PDOException $e) {
            error_log("Ошибка БД в register_phone: " . $e->getMessage());
            echo json_encode(['error' => 'database_error']);
        }
        break;
    
    // ===== ОТПРАВКА КОДА ПОДТВЕРЖДЕНИЯ =====
    case 'send_code':
        $data = json_decode(file_get_contents('php://input'), true);
        $chat_id = $data['chat_id'] ?? '';
        $phone = $data['phone'] ?? '';
        
        if (!$chat_id || !$phone) {
            echo json_encode(['error' => 'missing_data']);
            exit;
        }
        
        // Генерируем 6-значный код
        $code = sprintf("%06d", random_int(0, 999999));
        
        // Создаём таблицу для кодов, если её нет
        $db->exec("
            CREATE TABLE IF NOT EXISTS auth_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id TEXT NOT NULL,
                phone TEXT NOT NULL,
                code TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                used INTEGER DEFAULT 0,
                expires_at DATETIME DEFAULT (datetime('now', '+5 minutes'))
            )
        ");
        
        // Сохраняем код в БД
        $stmt = $db->prepare("INSERT INTO auth_codes (chat_id, phone, code) VALUES (?, ?, ?)");
        $stmt->execute([$chat_id, $phone, $code]);
        
        // Отправляем код в Telegram бота
        $telegram_url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
        $telegram_data = [
            'chat_id' => $chat_id,
            'text' => "🔑 *Код подтверждения*\n\n"
                    . "Ваш код для входа: `{$code}`\n\n"
                    . "⏱️ Код действителен 5 минут\n"
                    . "Никому не сообщайте этот код!",
            'parse_mode' => 'Markdown'
        ];
        
        $ch = curl_init($telegram_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $telegram_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($http_code == 200 && $result && $result['ok']) {
            echo json_encode([
                'success' => true, 
                'message' => 'Код отправлен в Telegram',
                'code_id' => $db->lastInsertId()
            ]);
        } else {
            error_log("Ошибка отправки кода в Telegram: " . $response);
            echo json_encode([
                'error' => 'failed_to_send',
                'details' => 'Не удалось отправить код в Telegram'
            ]);
        }
        break;
    
    // ===== ПРОВЕРКА КОДА =====
    case 'verify_code':
        $data = json_decode(file_get_contents('php://input'), true);
        $chat_id = $data['chat_id'] ?? '';
        $code = $data['code'] ?? '';
        
        if (!$chat_id || !$code) {
            echo json_encode(['error' => 'missing_data']);
            exit;
        }
        
        // Ищем неиспользованный код, который не истёк
        $stmt = $db->prepare("
            SELECT * FROM auth_codes 
            WHERE chat_id = ? AND code = ? AND used = 0 
            AND datetime(expires_at) > datetime('now')
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$chat_id, $code]);
        $auth = $stmt->fetch();
        
        if ($auth) {
            // Отмечаем код как использованный
            $stmt = $db->prepare("UPDATE auth_codes SET used = 1 WHERE id = ?");
            $stmt->execute([$auth['id']]);
            
            // Получаем пользователя
            $stmt = $db->prepare("SELECT * FROM users WHERE telegram_chat_id = ?");
            $stmt->execute([$chat_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Создаём сессию для входа
                $_SESSION['user_id'] = $user['id'];
                
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'phone' => $user['phone']
                    ]
                ]);
            } else {
                echo json_encode(['error' => 'user_not_found']);
            }
        } else {
            echo json_encode(['error' => 'invalid_code']);
        }
        break;
    
    // ===== ПОЛУЧИТЬ ПОЛЬЗОВАТЕЛЯ ПО НОМЕРУ =====
    case 'get_user_by_phone':
        $phone = $_GET['phone'] ?? '';
        
        if (!$phone) {
            echo json_encode(['error' => 'missing_phone']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT id, username, first_name, last_name, avatar FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
        }
        break;
    
    // ===== НЕИЗВЕСТНОЕ ДЕЙСТВИЕ =====
    default:
        echo json_encode(['error' => 'unknown_action']);
}
?>