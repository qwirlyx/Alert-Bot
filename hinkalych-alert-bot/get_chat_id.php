<?php
require_once __DIR__ . '/lib/Telegram.php';
$config = require __DIR__ . '/config.php';

$setupKey = (string)($config['setup_key'] ?? '');
$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    $key = (string)($_GET['key'] ?? '');
    if ($setupKey === '' || $setupKey === 'change_this_setup_key' || !hash_equals($setupKey, $key)) {
        http_response_code(403);
        echo 'Forbidden. Сначала поменяй setup_key в config.php и открой get_chat_id.php?key=ТВОЙ_КЛЮЧ';
        exit;
    }
}

$telegram = new Telegram((string)($config['bot_token'] ?? ''), $config['telegram_proxy'] ?? []);

if (($isCli && in_array('--delete-webhook', $argv ?? [], true)) || (!$isCli && isset($_GET['delete_webhook']))) {
    $res = $telegram->deleteWebhook(false);
    print_block('deleteWebhook result', $res, $isCli);
}

$updates = $telegram->getUpdates();
if ($isCli) {
    echo "Telegram updates:\n";
    print_r($updates);
    exit;
}

function print_block(string $title, array $data, bool $isCli): void
{
    if ($isCli) {
        echo $title . "\n";
        print_r($data);
        return;
    }
    echo '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2><pre>' . htmlspecialchars(print_r($data, true), ENT_QUOTES, 'UTF-8') . '</pre>';
}

function extract_rows(array $updates): array
{
    $rows = [];
    foreach (($updates['result'] ?? []) as $update) {
        $msg = $update['message'] ?? $update['channel_post'] ?? $update['edited_message'] ?? null;
        if (!$msg || empty($msg['chat'])) {
            continue;
        }
        $chat = $msg['chat'];
        $rows[] = [
            'chat_id' => $chat['id'] ?? '',
            'message_thread_id' => $msg['message_thread_id'] ?? '',
            'type' => $chat['type'] ?? '',
            'title' => $chat['title'] ?? (($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')),
            'text' => $msg['text'] ?? '',
            'date' => !empty($msg['date']) ? date('Y-m-d H:i:s', (int)$msg['date']) : '',
        ];
    }
    return $rows;
}

$rows = extract_rows($updates);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>HinkalychAlertBot chat_id</title>
    <style>
        body{font-family:Arial,sans-serif;background:#10141f;color:#e8edf7;margin:0;padding:30px}.card{max-width:1100px;margin:0 auto;background:#171d2b;border:1px solid #2d3650;border-radius:18px;padding:24px;box-shadow:0 12px 40px rgba(0,0,0,.25)}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{padding:10px 12px;border-bottom:1px solid #2d3650;text-align:left;vertical-align:top}code{background:#0b0f18;padding:3px 6px;border-radius:6px}.muted{color:#9aa7bd}.ok{color:#70e094}.warn{color:#ffd166}a{color:#8ab4ff}
    </style>
</head>
<body>
<div class="card">
    <h1>Поиск chat_id для HinkalychAlertBot</h1>
    <p class="muted">Добавь бота в нужный чат/канал, напиши там <code>/id</code> или <code>/start</code>, потом обнови эту страницу.</p>
    <p class="muted">Если Telegram пишет, что используется webhook, открой: <code>?key=ТВОЙ_КЛЮЧ&amp;delete_webhook=1</code></p>

    <?php if (empty($rows)): ?>
        <p class="warn">Пока нет сообщений. Напиши команду в нужном чате и обнови страницу.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>chat_id</th><th>message_thread_id</th><th>type</th><th>title</th><th>text</th><th>date</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><code><?= htmlspecialchars((string)$row['chat_id'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><code><?= htmlspecialchars((string)$row['message_thread_id'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string)$row['type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['text'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$row['date'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Полный ответ Telegram</h2>
    <pre><?= htmlspecialchars(print_r($updates, true), ENT_QUOTES, 'UTF-8') ?></pre>
</div>
</body>
</html>
