<?php
$bot_url = "http://178.104.40.37:25633/health";

$ch = curl_init($bot_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP код: $http_code<br>";
echo "Ошибка: " . ($error ?: 'нет') . "<br>";
echo "Ответ: " . htmlspecialchars($response) . "<br>";

if ($http_code == 200) {
    echo "✅ Соединение с ботом есть!";
} else {
    echo "❌ Нет соединения с ботом!";
}
?>