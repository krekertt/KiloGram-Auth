<?php
$ch = curl_init('https://api.telegram.org');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($response) {
    echo "✅ Сайт может подключиться к Telegram API";
} else {
    echo "❌ Ошибка: " . $error;
}
?>