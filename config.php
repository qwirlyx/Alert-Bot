<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');

$localConfigPath = __DIR__ . '/config.local.php';
$local = file_exists($localConfigPath) ? require $localConfigPath : [];

$config = [
    'bot_name' => 'HinkalychAlertBot',
    'bot_token' => '',
    'chat_id' => '',
    'message_thread_id' => null,
    'setup_key' => '',
    'telegram_proxy' => [
        'enabled' => false,
        'host' => '',
        'port' => '',
        'userpwd' => '',
    ],
    'check' => [
        'connect_timeout' => 5,
        'timeout' => 10,
        'fail_threshold' => 2,
        'recovery_threshold' => 1,
        'retries' => 1,
        'user_agent' => 'HinkalychAlertBot/1.0',
    ],
    'services' => [
        [
            'name' => 'Основной сайт',
            'type' => 'http',
            'url' => 'https://example.com/',
            'method' => 'GET',
            'expected_status' => [200, 399],
            'keyword' => null,
            'allow_insecure_ssl' => false,
        ],
        [
            'name' => 'API healthcheck',
            'type' => 'http',
            'url' => 'https://example.com/api/health',
            'method' => 'GET',
            'expected_status' => [200, 399],
            'keyword' => null,
            'allow_insecure_ssl' => false,
        ],
    ],
];

return array_replace_recursive($config, is_array($local) ? $local : []);
