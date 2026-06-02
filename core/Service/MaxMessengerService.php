<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

use function Uzelok\Core\logError;
use function Uzelok\Core\logLine;

/**
 * Уведомления в мессенджер MAX через Bot API (POST /messages?user_id=…).
 * Токен и user_id получателя — из корневого `.max.env`.
 */
final class MaxMessengerService
{
    private const API_BASE = 'https://platform-api.max.ru';

    public function __construct(
        private readonly string $token,
        private readonly int $notifyUserId,
        private readonly string $logPath,
    ) {
    }

    /**
     * @return self|null null если файла нет или не заданы token / user_id
     */
    public static function fromEnvFile(string $path, string $logPath): ?self
    {
        if (!is_readable($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $vars = self::parseKeyValueEnv($raw);
        $token = trim((string) ($vars['token'] ?? ''));
        $uid = (int) ($vars['user_id'] ?? 0);
        if ($token === '' || $uid < 1) {
            return null;
        }

        return new self($token, $uid, $logPath);
    }

    /**
     * @return array<string, string>
     */
    private static function parseKeyValueEnv(string $raw): array
    {
        $out = [];
        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if ($k !== '') {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    public function sendMessage(string $text): bool
    {
        if ($this->token === '') {
            return true;
        }

        $text = $this->truncateText($text, 4000);

        $url = self::API_BASE . '/messages?user_id=' . $this->notifyUserId;
        $payload = ['text' => $text];

        $ch = curl_init($url);
        if ($ch === false) {
            logError('MAX Messenger curl_init failed', $this->logPath);

            return false;
        }

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            logError('MAX Messenger JSON encode: ' . $e->getMessage(), $this->logPath);

            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->token,
                'Content-Type: application/json; charset=UTF-8',
            ],
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            logError('MAX Messenger curl error: ' . (string) $errno, $this->logPath);

            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            logError(
                'MAX Messenger HTTP ' . $httpCode . ': ' . (is_string($response) ? $response : ''),
                $this->logPath
            );

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $order
     */
    public function formatOrderNotification(array $order): string
    {
        $name = (string) ($order['name'] ?? '');
        $phone = (string) ($order['phone'] ?? '');
        $email = (string) ($order['email'] ?? '');
        $productTitle = trim((string) ($order['product_title'] ?? ''));
        $message = trim((string) ($order['message'] ?? ''));
        $datetime = (string) ($order['datetime'] ?? '');

        $source = (string) ($order['source'] ?? 'website');
        $headline = match ($source) {
            'workshop' => '🏭 МАСТЕРСКАЯ — Новая заявка с сайта uzelok64.ru',
            default => '🆕 Новая заявка с сайта uzelok64.ru',
        };

        $lines = [
            $headline,
            '',
            '👤 Имя: ' . $name,
            '📞 Телефон: ' . $phone,
            '📧 Email: ' . $email,
        ];

        if ($productTitle !== '') {
            $lines[] = '📦 Товар: ' . $productTitle;
        }

        if ($message !== '') {
            $lines[] = '💬 Комментарий: ' . $message;
        }

        $lines[] = '';
        $lines[] = '🕐 ' . $datetime;

        return implode("\n", $lines);
    }

    private function truncateText(string $text, int $maxLen): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        return mb_substr($text, 0, $maxLen - 3) . '...';
    }
}
