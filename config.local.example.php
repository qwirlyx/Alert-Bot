<?php

return [
    'bot_token' => 'TELEGRAM_BOT_TOKEN',
    'chat_id' => 'TELEGRAM_CHAT_ID',
    'message_thread_id' => null,
    'setup_key' => 'CHANGE_ME',

    'services' => [
        [
            'name' => 'Название сервиса',
            'type' => 'http',
            'url' => 'https://example.com/',
            'method' => 'GET',
            'expected_status' => [200, 399],
            'keyword' => null,
            'allow_insecure_ssl' => false,
        ],
    ],
];
