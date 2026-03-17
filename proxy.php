<?php
// proxy.php - исправленная версия
$token = '8081566708:AAHm4ppfiDQMVT_GCsTFmXXe-Z56UWae6AM';
$chat_id = $_GET['chat_id'] ?? $_POST['chat_id'] ?? '';
$code = $_GET['code'] ?? $_POST['code'] ?? '';

if (!$chat_id || !$code) {
    die(json_encode(['error' => 'missing_data']));
}

// Отправляем в Telegram
$url = "https://api.telegram.org/bot$token/sendMessage";
$data = [
    'chat_id' => $chat_id,
    'text' => "🔑 Код подтверждения: $code",
    'parse_mode' => 'HTML'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Логируем для отладки
file_put_contents('proxy_log.txt', date('Y-m-d H:i:s') . " - Code: $code, HTTP: $http_code, Error: $error\n", FILE_APPEND);

header('Content-Type: application/json');
if ($http_code == 200) {
    echo json_encode(['success' => true, 'response' => json_decode($response, true)]);
} else {
    echo json_encode([
        'success' => false, 
        'error' => $error,
        'http_code' => $http_code,
        'response' => $response
    ]);
}
?>