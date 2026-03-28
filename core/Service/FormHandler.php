<?php

declare(strict_types=1);

namespace Uzelok\Core\Service;

use DateTimeImmutable;
use DateTimeZone;
use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;

use function Uzelok\Core\logError;
use function Uzelok\Core\sanitize;

final class FormHandler
{
    public function __construct(
        private readonly Database $db,
        private readonly TelegramService $telegram,
        private readonly Product $productModel,
        private readonly array $emailConfig,
        private readonly string $logPath,
    ) {
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{success: true, message: string}|array{success: false, errors: list<string>}
     */
    public function processOrderForm(array $postData): array
    {
        $errors = $this->validate($postData);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $name = sanitize((string) ($postData['name'] ?? ''));
        $phone = trim((string) ($postData['phone'] ?? ''));
        $emailRaw = trim((string) ($postData['email'] ?? ''));
        $email = $emailRaw === '' ? '' : sanitize($emailRaw);
        $message = sanitize((string) ($postData['message'] ?? ''));
        $productId = isset($postData['product_id']) && $postData['product_id'] !== ''
            ? (int) $postData['product_id']
            : null;

        $source = (string) ($postData['source'] ?? 'website');
        if (!in_array($source, ['website', 'workshop'], true)) {
            $source = 'website';
        }

        $productTitle = '';
        if ($productId !== null && $productId > 0) {
            $row = $this->productModel->findById($productId);
            if ($row !== null) {
                $productTitle = (string) ($row['title'] ?? '');
            }
        }

        $sql = <<<'SQL'
INSERT INTO orders (product_id, customer_name, customer_phone, customer_email, message, source, status)
VALUES (:product_id, :customer_name, :customer_phone, :customer_email, :message, :source, :status)
SQL;

        $params = [
            ':product_id' => $productId,
            ':customer_name' => $name,
            ':customer_phone' => $phone,
            ':customer_email' => $email,
            ':message' => $message,
            ':source' => $source,
            ':status' => 'new',
        ];

        try {
            $this->db->query($sql, $params);
        } catch (\Throwable $e) {
            logError('FormHandler DB insert failed: ' . $e->getMessage(), $this->logPath);

            return ['success' => false, 'errors' => ['Не удалось сохранить заявку. Попробуйте позже.']];
        }

        $datetime = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $telegramPayload = [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'product_title' => $productTitle,
            'message' => $message,
            'datetime' => $datetime,
            'source' => $source,
        ];
        $text = $this->telegram->formatOrderNotification($telegramPayload);
        $this->telegram->sendMessage($text);

        $this->sendEmail([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'product_title' => $productTitle,
            'message' => $message,
            'datetime' => $datetime,
        ]);

        return [
            'success' => true,
            'message' => 'Заявка отправлена! Мы свяжемся с вами в ближайшее время.',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private function validate(array $data): array
    {
        $errors = [];
        $name = trim((string) ($data['name'] ?? ''));
        $len = mb_strlen($name);
        if ($len < 2 || $len > 100) {
            $errors[] = 'Имя: укажите от 2 до 100 символов.';
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        $phoneLen = strlen($phone);
        if ($phoneLen < 5 || $phoneLen > 20) {
            $errors[] = 'Телефон: укажите от 5 до 20 символов.';
        } elseif (!preg_match('/^[\d\+\-\(\)\s]+$/u', $phone)) {
            $errors[] = 'Телефон: допустимы цифры, +, -, скобки и пробелы.';
        }

        $source = (string) ($data['source'] ?? 'website');
        $email = trim((string) ($data['email'] ?? ''));
        if ($source === 'workshop' && $email === '') {
            $errors[] = 'Укажите email.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email указан некорректно.';
        }

        if (isset($data['product_id']) && $data['product_id'] !== '') {
            $pid = filter_var($data['product_id'], FILTER_VALIDATE_INT);
            if ($pid === false || $pid < 1) {
                $errors[] = 'Товар: неверный идентификатор.';
            }
        }

        $message = (string) ($data['message'] ?? '');
        if (mb_strlen($message) > 1000) {
            $errors[] = 'Комментарий: не более 1000 символов.';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $orderData
     */
    private function sendEmail(array $orderData): bool
    {
        $to = (string) ($this->emailConfig['to'] ?? '');
        $from = (string) ($this->emailConfig['from'] ?? '');
        if ($to === '' || $from === '') {
            return false;
        }

        $subject = 'Новая заявка с uzelok64.ru';
        $body = implode("\n", [
            'Имя: ' . ($orderData['name'] ?? ''),
            'Телефон: ' . ($orderData['phone'] ?? ''),
            'Email: ' . ($orderData['email'] ?? ''),
            'Товар: ' . ($orderData['product_title'] ?? ''),
            'Комментарий: ' . ($orderData['message'] ?? ''),
            'Время: ' . ($orderData['datetime'] ?? ''),
        ]);

        $fromName = (string) ($this->emailConfig['from_name'] ?? '');
        $headers = [];
        if ($fromName !== '') {
            $headers[] = 'From: ' . $this->encodeMimeHeader($fromName) . ' <' . $from . '>';
        } else {
            $headers[] = 'From: ' . $from;
        }
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$ok) {
            logError('FormHandler mail() failed', $this->logPath);
        }

        return $ok;
    }

    private function encodeMimeHeader(string $text): string
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
