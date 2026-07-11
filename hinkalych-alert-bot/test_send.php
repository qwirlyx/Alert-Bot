<?php
require_once __DIR__ . '/lib/Telegram.php';
$config = require __DIR__ . '/config.php';

$setupKey = (string)($config['setup_key'] ?? '');
$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $key = (string)($_GET['key'] ?? '');
    if ($setupKey === '' || $setupKey === 'change_this_setup_key' || !hash_equals($setupKey, $key)) {
        http_response_code(403);
        echo "Forbidden. Поменяй setup_key в config.php и открой test_send.php?key=ТВОЙ_КЛЮЧ\n";
        exit;
    }
}

$telegram = new Telegram((string)($config['bot_token'] ?? ''), $config['telegram_proxy'] ?? []);
$threadId = $config['message_thread_id'] ?? null;
$threadId = ($threadId === null || $threadId === '') ? null : (int)$threadId;

$res = $telegram->sendMessage((string)($config['chat_id'] ?? ''), "🧪 <b>Тест HinkalychAlertBot</b>\nЕсли ты видишь это сообщение, токен и chat_id настроены правильно.", $threadId);
print_r($res);
