<?php
require_once __DIR__ . '/lib/Logger.php';
require_once __DIR__ . '/lib/Telegram.php';

$config = require __DIR__ . '/config.php';
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}

$logger = new Logger($dataDir . '/app.log');
$lockFile = $dataDir . '/monitor.lock';
$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    exit("Monitor is already running.\n");
}

try {
    $stateFile = $dataDir . '/state.json';
    $state = load_state($stateFile);
    $telegram = new Telegram((string)($config['bot_token'] ?? ''), $config['telegram_proxy'] ?? []);

    foreach (($config['services'] ?? []) as $service) {
        if (empty($service['name']) || empty($service['type'])) {
            continue;
        }

        $key = service_key($service);
        $old = $state['services'][$key] ?? [
            'status' => 'unknown',
            'fail_count' => 0,
            'recovery_count' => 0,
            'last_error' => null,
            'last_change' => null,
        ];

        $result = check_service_with_retries($service, $config['check'] ?? []);
        $new = $old;
        $new['name'] = $service['name'];
        $new['target'] = service_target($service);
        $new['last_checked'] = date('Y-m-d H:i:s');
        $new['last_error'] = $result['ok'] ? null : $result['error'];
        $new['last_http_code'] = $result['http_code'] ?? null;

        $failThreshold = max(1, (int)($config['check']['fail_threshold'] ?? 2));
        $recoveryThreshold = max(1, (int)($config['check']['recovery_threshold'] ?? 1));

        if ($result['ok']) {
            $new['fail_count'] = 0;
            $new['recovery_count'] = (int)($old['recovery_count'] ?? 0) + 1;

            if (($old['status'] ?? 'unknown') === 'down' && $new['recovery_count'] >= $recoveryThreshold) {
                $new['status'] = 'up';
                $new['last_change'] = date('Y-m-d H:i:s');
                send_alert($telegram, $config, build_recovered_message($service, $result, $old));
                $logger->info('Service recovered', ['service' => $service['name']]);
            } elseif (($old['status'] ?? 'unknown') === 'unknown') {
                $new['status'] = 'up';
                $new['last_change'] = date('Y-m-d H:i:s');
            }
        } else {
            $new['recovery_count'] = 0;
            $new['fail_count'] = (int)($old['fail_count'] ?? 0) + 1;

            if (($old['status'] ?? 'unknown') !== 'down' && $new['fail_count'] >= $failThreshold) {
                $new['status'] = 'down';
                $new['last_change'] = date('Y-m-d H:i:s');
                send_alert($telegram, $config, build_down_message($service, $result, $new['fail_count']));
                $logger->warning('Service down', ['service' => $service['name'], 'error' => $result['error']]);
            }
        }

        $state['services'][$key] = $new;
    }

    $state['updated_at'] = date('Y-m-d H:i:s');
    save_state($stateFile, $state);
} catch (Throwable $e) {
    $logger->error('Monitor fatal error', ['error' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
} finally {
    if (isset($lockHandle) && $lockHandle) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function load_state(string $file): array
{
    if (!is_file($file)) {
        return ['services' => []];
    }
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : ['services' => []];
}

function save_state(string $file, array $state): void
{
    file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function service_key(array $service): string
{
    return sha1(($service['name'] ?? '') . '|' . service_target($service));
}

function service_target(array $service): string
{
    if (($service['type'] ?? '') === 'http') {
        return (string)($service['url'] ?? '');
    }
    if (($service['type'] ?? '') === 'tcp') {
        return (string)($service['host'] ?? '') . ':' . (string)($service['port'] ?? '');
    }
    return (string)($service['name'] ?? 'unknown');
}

function check_service_with_retries(array $service, array $checkConfig): array
{
    $attempts = max(1, (int)($checkConfig['retries'] ?? 0) + 1);
    $last = ['ok' => false, 'error' => 'Not checked'];

    for ($i = 1; $i <= $attempts; $i++) {
        $last = check_service($service, $checkConfig);
        if ($last['ok']) {
            return $last;
        }
        if ($i < $attempts) {
            usleep(300000);
        }
    }

    return $last;
}

function check_service(array $service, array $checkConfig): array
{
    $type = strtolower((string)($service['type'] ?? ''));
    if ($type === 'http') {
        return check_http($service, $checkConfig);
    }
    if ($type === 'tcp') {
        return check_tcp($service, $checkConfig);
    }
    return ['ok' => false, 'error' => 'Unknown service type: ' . $type];
}

function check_http(array $service, array $checkConfig): array
{
    $url = (string)($service['url'] ?? '');
    if ($url === '') {
        return ['ok' => false, 'error' => 'HTTP URL is empty'];
    }

    $method = strtoupper((string)($service['method'] ?? 'GET'));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => (int)($checkConfig['connect_timeout'] ?? 5),
        CURLOPT_TIMEOUT => (int)($checkConfig['timeout'] ?? 10),
        CURLOPT_USERAGENT => (string)($checkConfig['user_agent'] ?? 'HinkalychAlertBot/1.0'),
        CURLOPT_SSL_VERIFYPEER => empty($service['allow_insecure_ssl']),
        CURLOPT_SSL_VERIFYHOST => empty($service['allow_insecure_ssl']) ? 2 : 0,
    ]);

    if ($method === 'HEAD') {
        curl_setopt($ch, CURLOPT_NOBODY, true);
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    }

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'error' => 'cURL: ' . $err, 'http_code' => $httpCode, 'time' => $totalTime];
    }

    $expected = $service['expected_status'] ?? [200, 399];
    $min = (int)($expected[0] ?? 200);
    $max = (int)($expected[1] ?? 399);
    if ($httpCode < $min || $httpCode > $max) {
        return ['ok' => false, 'error' => 'Unexpected HTTP status: ' . $httpCode, 'http_code' => $httpCode, 'time' => $totalTime];
    }

    $keyword = $service['keyword'] ?? null;
    if ($keyword !== null && $keyword !== '' && strpos((string)$body, (string)$keyword) === false) {
        return ['ok' => false, 'error' => 'Keyword not found: ' . $keyword, 'http_code' => $httpCode, 'time' => $totalTime];
    }

    return ['ok' => true, 'error' => null, 'http_code' => $httpCode, 'time' => $totalTime];
}

function check_tcp(array $service, array $checkConfig): array
{
    $host = (string)($service['host'] ?? '');
    $port = (int)($service['port'] ?? 0);
    $timeout = (int)($checkConfig['connect_timeout'] ?? 5);

    if ($host === '' || $port <= 0) {
        return ['ok' => false, 'error' => 'TCP host/port is empty'];
    }

    $start = microtime(true);
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $time = round(microtime(true) - $start, 3);

    if (!$fp) {
        return ['ok' => false, 'error' => "TCP connect failed: {$errstr} ({$errno})", 'time' => $time];
    }

    fclose($fp);
    return ['ok' => true, 'error' => null, 'time' => $time];
}

function send_alert(Telegram $telegram, array $config, string $message): void
{
    $chatId = (string)($config['chat_id'] ?? '');
    $threadId = $config['message_thread_id'] ?? null;
    $threadId = ($threadId === null || $threadId === '') ? null : (int)$threadId;

    $res = $telegram->sendMessage($chatId, $message, $threadId);
    if (empty($res['ok'])) {
        $logger = new Logger(__DIR__ . '/data/app.log');
        $logger->error('Telegram send failed', ['response' => $res]);
    }
}

function build_down_message(array $service, array $result, int $failCount): string
{
    $target = service_target($service);
    $time = date('Y-m-d H:i:s');
    $http = isset($result['http_code']) && $result['http_code'] ? "\nHTTP: <code>" . e((string)$result['http_code']) . "</code>" : '';

    return "🚨 <b>Сервис упал</b>\n"
        . "\n<b>" . e((string)$service['name']) . "</b>"
        . "\nЦель: <code>" . e($target) . "</code>"
        . $http
        . "\nОшибка: <code>" . e((string)($result['error'] ?? 'unknown')) . "</code>"
        . "\nПровалов подряд: <code>" . e((string)$failCount) . "</code>"
        . "\nВремя: <code>" . e($time) . "</code>";
}

function build_recovered_message(array $service, array $result, array $old): string
{
    $target = service_target($service);
    $time = date('Y-m-d H:i:s');
    $downSince = !empty($old['last_change']) ? $old['last_change'] : 'неизвестно';
    $http = isset($result['http_code']) && $result['http_code'] ? "\nHTTP: <code>" . e((string)$result['http_code']) . "</code>" : '';

    return "✅ <b>Сервис восстановился</b>\n"
        . "\n<b>" . e((string)$service['name']) . "</b>"
        . "\nЦель: <code>" . e($target) . "</code>"
        . $http
        . "\nПадал с: <code>" . e((string)$downSince) . "</code>"
        . "\nВосстановился: <code>" . e($time) . "</code>";
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
