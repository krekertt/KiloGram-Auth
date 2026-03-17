<?php
// Тест разных способов отправки
$token = '8081566708:AAHm4ppfiDQMVT_GCsTFmXXe-Z56UWae6AM';
$chat_id = '1726423121'; // Твой chat_id
$code = rand(100000, 999999);

echo "<h2>🔍 Тест отправки в Telegram</h2>";

// Способ 1: cURL
echo "<h3>Способ 1: cURL</h3>";
$url = "https://api.telegram.org/bot$token/sendMessage";
$data = [
    'chat_id' => $chat_id,
    'text' => "🔑 Код: $code"
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP код: " . $info['http_code'] . "<br>";
echo "Ошибка: " . ($error ?: 'нет') . "<br>";
echo "Ответ: " . htmlspecialchars($response) . "<br>";

// Способ 2: file_get_contents с контекстом
echo "<h3>Способ 2: file_get_contents</h3>";
$url2 = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode("🔑 Код: $code");

$context = stream_context_create([
    'http' => ['timeout' => 10],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
]);

$response2 = @file_get_contents($url2, false, $context);
echo "Ответ: " . htmlspecialchars($response2 ?: 'нет ответа') . "<br>";

// Способ 3: через сокет (самый низкоуровневый)
echo "<h3>Способ 3: Прямой сокет</h3>";
$host = 'api.telegram.org';
$port = 443;
$path = "/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode("🔑 Код: $code");

$fp = @fsockopen("ssl://$host", $port, $errno, $errstr, 10);
if ($fp) {
    $out = "GET $path HTTP/1.1\r\n";
    $out .= "Host: $host\r\n";
    $out .= "Connection: Close\r\n\r\n";
    fwrite($fp, $out);
    $response3 = '';
    while (!feof($fp)) {
        $response3 .= fgets($fp, 128);
    }
    fclose($fp);
    echo "Соединение успешно!<br>";
    echo "Первые 200 символов ответа: " . htmlspecialchars(substr($response3, 0, 200)) . "<br>";
} else {
    echo "Ошибка сокета: $errno - $errstr<br>";
}

echo "<hr>";
echo "Твой chat_id: $chat_id<br>";
echo "Сгенерированный код: $code<br>";
?>