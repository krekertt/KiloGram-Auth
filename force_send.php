<?php
require_once 'config.php';

$phone = '+3958402036';
$chat_id = 5898976754;

// Генерируем код
$code = rand(100000, 999999);

// URL твоего бота (он не заблокирован)
$bot_url = "http://178.104.40.37:25633/send_direct";

$data = [
    'chat_id' => $chat_id,
    'text' => "🔑 Код подтверждения: $code"
];

$ch = curl_init($bot_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP код: $http_code<br>";
echo "Ответ: $response<br>";
echo "Код: $code<br>";
?>