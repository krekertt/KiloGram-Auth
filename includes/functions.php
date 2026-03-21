<?php
date_default_timezone_set('Europe/Moscow');

// ============================================

// ФУНКЦИИ ДЛЯ РАБОТЫ С ПОЛЬЗОВАТЕЛЯМИ

// ============================================

/**

 * Получить пользователя по ID

 */

function getUserById($db, $user_id) {

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");

    $stmt->execute([$user_id]);

    return $stmt->fetch();

}

/**

 * Обновить статус пользователя

 */

function updateUserStatus($db, $user_id, $status = 'online') {

    $stmt = $db->prepare("UPDATE users SET status = ?, last_seen = CURRENT_TIMESTAMP WHERE id = ?");

    $stmt->execute([$status, $user_id]);

}

/**

 * Получить количество непрочитанных сообщений

 */

function getUnreadCount($db, $user_id) {

    $stmt = $db->prepare("

        SELECT COUNT(*) as count 

        FROM messages m

        LEFT JOIN message_status ms ON m.id = ms.message_id AND ms.user_id = ?

        WHERE m.user_id != ? AND (ms.status IS NULL OR ms.status != 'read')

    ");

    $stmt->execute([$user_id, $user_id]);

    return $stmt->fetch()['count'];

}

// ============================================

// ФУНКЦИИ ДЛЯ РАБОТЫ С ЧАТАМИ

// ============================================

/**

 * Получить чат по ID

 */

function getChatById($db, $chat_id, $user_id) {

    $stmt = $db->prepare("

        SELECT 

            c.*,

            CASE 

                WHEN c.type = 'private' THEN (

                    SELECT username FROM users 

                    WHERE id IN (

                        SELECT user_id FROM chat_participants 

                        WHERE chat_id = c.id AND user_id != ?

                    )

                )

                ELSE c.name

            END as display_name

        FROM chats c

        JOIN chat_participants cp ON c.id = cp.chat_id

        WHERE c.id = ? AND cp.user_id = ?

    ");

    $stmt->execute([$user_id, $chat_id, $user_id]);

    return $stmt->fetch();

}

/**

 * Получить участников чата

 */

function getChatParticipants($db, $chat_id) {

    $stmt = $db->prepare("

        SELECT u.*, cp.role, cp.joined_at

        FROM chat_participants cp

        JOIN users u ON cp.user_id = u.id

        WHERE cp.chat_id = ?

    ");

    $stmt->execute([$chat_id]);

    return $stmt->fetchAll();

}

// ============================================

// ФУНКЦИИ ДЛЯ РАБОТЫ С СООБЩЕНИЯМИ

// ============================================

/**

 * Отметить сообщения как прочитанные

 */

function markMessagesAsRead($db, $chat_id, $user_id) {

    $stmt = $db->prepare("

        INSERT OR REPLACE INTO message_status (message_id, user_id, status, updated_at)

        SELECT id, ?, 'read', CURRENT_TIMESTAMP

        FROM messages

        WHERE chat_id = ? AND user_id != ?

    ");

    return $stmt->execute([$user_id, $chat_id, $user_id]);

}

// ============================================

// ФУНКЦИИ ДЛЯ ЗАГРУЗКИ ФАЙЛОВ

// ============================================

define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50 MB

define('ALLOWED_IMAGE_TYPES', [

    'image/jpeg', 

    'image/png', 

    'image/gif', 

    'image/webp',

    'image/bmp',

    'image/svg+xml'

]);

define('ALLOWED_VIDEO_TYPES', [

    'video/mp4', 

    'video/webm', 

    'video/ogg', 

    'video/quicktime',

    'video/x-msvideo', // avi

    'video/x-matroska', // mkv

    'video/3gpp',

    'video/mpeg'

]);

define('ALLOWED_AUDIO_TYPES', [

    'audio/mpeg',      // .mp3

    'audio/mp3',       // .mp3 альтернативный

    'audio/ogg',       // .ogg

    'audio/wav',       // .wav

    'audio/x-wav',     // .wav альтернативный

    'audio/webm',      // .webm

    'audio/mp4',       // .mp4

    'audio/x-m4a',     // .m4a

    'audio/aac',       // .aac

    'audio/amr',       // .amr (некоторые телефоны)

    'audio/3gpp',      // .3gp

    'audio/mp4a-latm', // MPEG-4 аудио

    'audio/x-matroska', // .mka

    'audio/flac',      // .flac

    'audio/x-flac',    // .flac альтернативный

    'audio/vnd.wave',  // wave

    'audio/wave'       // wave

]);

/**

 * Загрузка файла на сервер

 */

function uploadFile($file, $type = 'image') {

    if ($file['error'] !== UPLOAD_ERR_OK) {

        return ['error' => 'Ошибка загрузки файла: ' . getUploadError($file['error'])];

    }

    

    if ($file['size'] > MAX_FILE_SIZE) {

        $size_mb = MAX_FILE_SIZE / 1024 / 1024;

        return ['error' => "Файл слишком большой (макс. {$size_mb} MB)"];

    }

    

    // Определяем разрешённые типы

    $allowed_types = [];

    switch ($type) {

        case 'image':

            $allowed_types = ALLOWED_IMAGE_TYPES;

            break;

        case 'video':

            $allowed_types = ALLOWED_VIDEO_TYPES;

            break;

        case 'audio':

            $allowed_types = ALLOWED_AUDIO_TYPES;

            break;

        default:

            return ['error' => 'Неизвестный тип файла'];

    }

    

    // Получаем MIME-тип несколькими способами для надёжности

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    $mime_type = finfo_file($finfo, $file['tmp_name']);

    finfo_close($finfo);

    

    // Если finfo не сработал, используем переданный тип

    if (!$mime_type || $mime_type === 'application/octet-stream') {

        $mime_type = $file['type'];

    }

    

    // Для webm аудио может приходить как video/webm

    if (strpos($mime_type, 'video/webm') !== false && $type === 'audio') {

        $mime_type = 'audio/webm';

    }

    

    // Для mp4 аудио

    if (strpos($mime_type, 'video/mp4') !== false && $type === 'audio') {

        $mime_type = 'audio/mp4';

    }

    

    // Для 3gp аудио

    if (strpos($mime_type, 'video/3gpp') !== false && $type === 'audio') {

        $mime_type = 'audio/3gpp';

    }

    

    // Отладка - записываем в лог

    error_log("Загружается файл типа: {$type}, MIME-тип: {$mime_type}, Имя: {$file['name']}, Размер: {$file['size']}");

    

    // Проверяем MIME-тип

    if (!in_array($mime_type, $allowed_types)) {

        return ['error' => 'Неподдерживаемый тип файла. Разрешены: ' . implode(', ', $allowed_types)];

    }

    

    // Создаём папку по типу и дате

    $date_dir = date('Y/m/d');

    $upload_dir = __DIR__ . '/../uploads/' . $date_dir . '/';

    if (!file_exists($upload_dir)) {

        mkdir($upload_dir, 0777, true);

    }

    

    // Генерируем уникальное имя

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

    $filename = uniqid() . '_' . time() . '.' . $ext;

    $filepath = $upload_dir . $filename;

    

    if (move_uploaded_file($file['tmp_name'], $filepath)) {

        // Создаём миниатюру для изображений

        $thumbnail = null;

        if (strpos($mime_type, 'image/') === 0) {

            $thumbnail = createThumbnail($filepath);

        }

        

        // Для аудио можно добавить получение длительности

        $duration = 0;

        if ($type === 'audio') {

            // Здесь можно будет добавить получение длительности через getID3 или другую библиотеку

        }

        

        return [

            'success' => true,

            'path' => 'uploads/' . $date_dir . '/' . $filename,

            'name' => $file['name'],

            'size' => $file['size'],

            'mime_type' => $mime_type,

            'thumbnail' => $thumbnail,

            'duration' => $duration

        ];

    }

    

    return ['error' => 'Ошибка при сохранении файла'];

}

/**

 * Получить текст ошибки загрузки

 */

function getUploadError($code) {

    switch ($code) {

        case UPLOAD_ERR_INI_SIZE:

        case UPLOAD_ERR_FORM_SIZE:

            return 'Файл превышает максимальный размер';

        case UPLOAD_ERR_PARTIAL:

            return 'Файл был загружен только частично';

        case UPLOAD_ERR_NO_FILE:

            return 'Файл не был загружен';

        case UPLOAD_ERR_NO_TMP_DIR:

            return 'Отсутствует временная папка';

        case UPLOAD_ERR_CANT_WRITE:

            return 'Ошибка записи на диск';

        case UPLOAD_ERR_EXTENSION:

            return 'Загрузка остановлена расширением';

        default:

            return 'Неизвестная ошибка';

    }

}

/**

 * Создание миниатюры для изображения

 */

function createThumbnail($source_path, $max_width = 200, $max_height = 200) {

    if (!file_exists($source_path)) {

        return null;

    }

    

    $info = getimagesize($source_path);

    if (!$info) return null;

    

    list($width, $height) = $info;

    $type = $info[2];

    

    // Создаём исходное изображение

    switch ($type) {

        case IMAGETYPE_JPEG:

            $src = imagecreatefromjpeg($source_path);

            break;

        case IMAGETYPE_PNG:

            $src = imagecreatefrompng($source_path);

            // Сохраняем прозрачность

            imagealphablending($src, true);

            imagesavealpha($src, true);

            break;

        case IMAGETYPE_GIF:

            $src = imagecreatefromgif($source_path);

            break;

        case IMAGETYPE_WEBP:

            $src = imagecreatefromwebp($source_path);

            break;

        case IMAGETYPE_BMP:

            $src = imagecreatefrombmp($source_path);

            break;

        default:

            return null;

    }

    

    if (!$src) return null;

    

    // Вычисляем новые размеры

    $ratio = min($max_width / $width, $max_height / $height);

    $new_width = round($width * $ratio);

    $new_height = round($height * $ratio);

    

    // Создаём миниатюру

    $thumb = imagecreatetruecolor($new_width, $new_height);

    

    // Сохраняем прозрачность для PNG

    if ($type == IMAGETYPE_PNG) {

        imagealphablending($thumb, false);

        imagesavealpha($thumb, true);

        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);

        imagefilledrectangle($thumb, 0, 0, $new_width, $new_height, $transparent);

    }

    

    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    

    // Сохраняем

    $path_info = pathinfo($source_path);

    $thumb_path = $path_info['dirname'] . '/thumb_' . $path_info['basename'];

    

    switch ($type) {

        case IMAGETYPE_JPEG:

            imagejpeg($thumb, $thumb_path, 80);

            break;

        case IMAGETYPE_PNG:

            imagepng($thumb, $thumb_path, 8);

            break;

        case IMAGETYPE_GIF:

            imagegif($thumb, $thumb_path);

            break;

        case IMAGETYPE_WEBP:

            imagewebp($thumb, $thumb_path, 80);

            break;

        case IMAGETYPE_BMP:

            imagebmp($thumb, $thumb_path);

            break;

    }

    

    imagedestroy($src);

    imagedestroy($thumb);

    

    return 'uploads/' . date('Y/m/d') . '/thumb_' . $path_info['basename'];

}

/**

 * Форматирование размера файла

 */

function formatFileSize($bytes) {

    if ($bytes < 1024) return $bytes . ' B';

    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';

    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';

    return round($bytes / 1073741824, 1) . ' GB';

}

// ============================================

// ФУНКЦИИ БЕЗОПАСНОСТИ

// ============================================

/**

 * Санитизация ввода

 */

function sanitizeInput($data) {

    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');

}

/**

 * Генерация CSRF токена

 */

function generateCSRFToken() {

    if (!isset($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    }

    return $_SESSION['csrf_token'];

}

/**

 * Проверка CSRF токена

 */

function verifyCSRFToken($token) {

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);

}

// ============================================

// ФОРМАТИРОВАНИЕ ВРЕМЕНИ

// ============================================

/**

 * Форматирование времени (только что, минуты назад и т.д.)

 */

function timeAgo($datetime) {

    $time = strtotime($datetime);

    $now = time();

    $diff = $now - $time;

    

    if ($diff < 60) {

        return 'только что';

    } elseif ($diff < 3600) {

        $min = floor($diff / 60);

        return $min . ' ' . pluralize($min, 'минута', 'минуты', 'минут') . ' назад';

    } elseif ($diff < 86400) {

        $hour = floor($diff / 3600);

        return $hour . ' ' . pluralize($hour, 'час', 'часа', 'часов') . ' назад';

    } elseif ($diff < 2592000) {

        $day = floor($diff / 86400);

        return $day . ' ' . pluralize($day, 'день', 'дня', 'дней') . ' назад';

    } else {

        return date('d.m.Y', $time);

    }

}

/**

 * Склонение слов (1 минута, 2 минуты, 5 минут)

 */

function pluralize($n, $form1, $form2, $form5) {

    $n = abs($n) % 100;

    $n1 = $n % 10;

    if ($n > 10 && $n < 20) return $form5;

    if ($n1 > 1 && $n1 < 5) return $form2;

    if ($n1 == 1) return $form1;

    return $form5;

}

// ============================================

// ОТЛАДКА

// ============================================

/**

 * Простая отладка (выводит переменную в читаемом виде)

 */

function debug($var, $title = '') {

    echo '<pre style="background: #f4f4f4; padding: 10px; margin: 10px; border: 1px solid #ccc; border-radius: 5px;">';

    if ($title) echo "<strong>$title:</strong>\n";

    print_r($var);

    echo '</pre>';

}

/**

 * Логирование ошибок

 */

function logError($message, $context = []) {

    $log_file = __DIR__ . '/../logs/error.log';

    $log_dir = dirname($log_file);

    

    if (!file_exists($log_dir)) {

        mkdir($log_dir, 0777, true);

    }

    

    $timestamp = date('Y-m-d H:i:s');

    $context_str = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

    $log_message = "[$timestamp] $message$context_str\n";

    

    file_put_contents($log_file, $log_message, FILE_APPEND);

}

?>
