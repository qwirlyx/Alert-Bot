<?php
class Telegram
{
    private string $token;
    private array $proxy;

    public function __construct(string $token, array $proxy = [])
    {
        $this->token = trim($token);
        $this->proxy = $proxy;
    }

    public function sendMessage(string $chatId, string $text, ?int $messageThreadId = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($messageThreadId !== null && $messageThreadId > 0) {
            $payload['message_thread_id'] = $messageThreadId;
        }

        return $this->request('sendMessage', $payload);
    }

    public function getUpdates(): array
    {
        return $this->request('getUpdates', [
            'timeout' => 0,
            'allowed_updates' => json_encode(['message', 'channel_post', 'edited_message', 'my_chat_member'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        return $this->request('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates ? 'true' : 'false',
        ]);
    }

    private function request(string $method, array $payload): array
    {
        if ($this->token === '' || strpos($this->token, 'PASTE_') === 0) {
            return ['ok' => false, 'description' => 'Bot token is empty or not configured'];
        }

        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
        ]);

        if (!empty($this->proxy['enabled']) && !empty($this->proxy['host']) && !empty($this->proxy['port'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy['host'] . ':' . $this->proxy['port']);
            if (!empty($this->proxy['userpwd'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy['userpwd']);
            }
        }

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'description' => 'cURL error: ' . $err, 'http_code' => $httpCode];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'description' => 'Bad JSON from Telegram', 'http_code' => $httpCode, 'raw' => $raw];
        }

        $decoded['http_code'] = $httpCode;
        return $decoded;
    }
}
