<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

use function Uzelok\Core\logError;
use function Uzelok\Core\logLine;

final class TelegramService
{
    public function __construct(
        private readonly string $botToken,
        private readonly string $chatId,
        private readonly string $logPath,
    ) {
    }

    public function sendMessage(string $text, string $parseMode = 'HTML'): bool
    {
        if ($this->usesPlaceholderToken()) {
            logLine(
                'INFO',
                'Telegram disabled (placeholder token), message not sent',
                $this->logPath
            );

            return true;
        }

        $url = 'https://api.telegram.org/bot' . $this->botToken . '/sendMessage';
        $payload = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            logError('Telegram curl_init failed', $this->logPath);

            return false;
        }

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            logError('Telegram JSON encode: ' . $e->getMessage(), $this->logPath);

            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            logError('Telegram curl error: ' . $errno, $this->logPath);

            return false;
        }

        if ($httpCode !== 200) {
            logError('Telegram HTTP ' . $httpCode . ': ' . (is_string($response) ? $response : ''), $this->logPath);

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

    private function usesPlaceholderToken(): bool
    {
        return $this->botToken === 'YOUR_TELEGRAM_BOT_TOKEN' || $this->botToken === '';
    }
}
