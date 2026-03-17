<?php
// proxy_send.php - использует file_get_contents вместо curl
$token = '8081566708:AAHm4ppfiDQMVT_GCsTFmXXe-Z56UWae6AM';
$chat_id = $_POST['chat_id'] ?? $_GET['chat_id'] ?? '';
$code = $_POST['code'] ?? $_GET['code'] ?? '';

if (!$chat_id || !$code) {
    die(json_encode(['error' => 'missing_data']));
}

$url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode("🔑 Код подтверждения: $code");

$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'ignore_errors' => true
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo json_encode(['error' => 'failed', 'url' => $url]);
} else {
    echo $response;
}
?>