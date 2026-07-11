<?php
$config = require __DIR__ . '/config.php';
$stateFile = __DIR__ . '/data/state.json';
$state = is_file($stateFile) ? json_decode((string)file_get_contents($stateFile), true) : ['services' => []];
if (!is_array($state)) {
    $state = ['services' => []];
}

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function service_target_web(array $service): string {
    if (($service['type'] ?? '') === 'http') return (string)($service['url'] ?? '');
    if (($service['type'] ?? '') === 'tcp') return (string)($service['host'] ?? '') . ':' . (string)($service['port'] ?? '');
    return (string)($service['name'] ?? 'unknown');
}
function service_key_web(array $service): string { return sha1(($service['name'] ?? '') . '|' . service_target_web($service)); }
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>HinkalychAlertBot</title>
    <style>
        :root{--bg:#10141f;--card:#171d2b;--line:#2d3650;--text:#e8edf7;--muted:#9aa7bd;--ok:#70e094;--bad:#ff6b6b;--unk:#ffd166}*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:radial-gradient(circle at top left,#1b2540,#10141f 45%);color:var(--text);padding:28px}.wrap{max-width:1100px;margin:0 auto}.top{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;margin-bottom:20px}.top h1{margin:0;font-size:30px}.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}.card{background:rgba(23,29,43,.94);border:1px solid var(--line);border-radius:20px;padding:18px;box-shadow:0 14px 40px rgba(0,0,0,.24)}.name{font-weight:700;font-size:18px;margin-bottom:10px}.badge{display:inline-block;border-radius:999px;padding:5px 10px;font-weight:700;font-size:12px;text-transform:uppercase}.up{background:rgba(112,224,148,.13);color:var(--ok);border:1px solid rgba(112,224,148,.35)}.down{background:rgba(255,107,107,.13);color:var(--bad);border:1px solid rgba(255,107,107,.35)}.unknown{background:rgba(255,209,102,.13);color:var(--unk);border:1px solid rgba(255,209,102,.35)}code{background:#0b0f18;border:1px solid #232b40;border-radius:8px;padding:3px 6px;color:#dbe7ff;word-break:break-all}.row{margin-top:10px;line-height:1.45}.label{color:var(--muted);font-size:13px;margin-bottom:4px}.footer{margin-top:18px;color:var(--muted);font-size:13px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>HinkalychAlertBot</h1>
            <div class="muted">Панель состояния проверок. Уведомления отправляет cron.</div>
        </div>
        <div class="muted">Обновлено: <?= h($state['updated_at'] ?? 'ещё не запускался') ?></div>
    </div>

    <div class="grid">
        <?php foreach (($config['services'] ?? []) as $service):
            $key = service_key_web($service);
            $row = $state['services'][$key] ?? [];
            $status = $row['status'] ?? 'unknown';
        ?>
            <div class="card">
                <div class="name"><?= h($service['name'] ?? 'Без названия') ?></div>
                <span class="badge <?= h($status) ?>"><?= h($status) ?></span>
                <div class="row">
                    <div class="label">Цель</div>
                    <code><?= h(service_target_web($service)) ?></code>
                </div>
                <div class="row">
                    <div class="label">Последняя проверка</div>
                    <?= h($row['last_checked'] ?? 'нет данных') ?>
                </div>
                <div class="row">
                    <div class="label">Последнее изменение</div>
                    <?= h($row['last_change'] ?? 'нет данных') ?>
                </div>
                <?php if (!empty($row['last_error'])): ?>
                    <div class="row">
                        <div class="label">Ошибка</div>
                        <code><?= h($row['last_error']) ?></code>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="footer">Файлы состояния и логов лежат в папке <code>data/</code>.</div>
</div>
</body>
</html>
