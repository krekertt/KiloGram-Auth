<?php
// telegram_send.php - вызывается только с твоего сервера
$token = '8081566708:AAHm4ppfiDQMVT_GCsTFmXXe-Z56UWae6AM';
$chat_id = $_POST['chat_id'] ?? '';
$code = $_POST['code'] ?? '';

if (!$chat_id || !$code) {
    die('Missing data');
}

$url = "https://api.telegram.org/bot$token/sendMessage";
$data = [
    'chat_id' => $chat_id,
    'text' => "🔑 Код подтверждения: $code"
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo $http_code == 200 ? 'ok' : 'error';
?>