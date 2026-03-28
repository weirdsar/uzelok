<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;
use Uzelok\Core\Service\FormHandler;
use Uzelok\Core\Service\TelegramService;

use function Uzelok\Core\validateCsrfToken;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Method not allowed']], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @var array<string, mixed> $config */
$config = require __DIR__ . '/../config/config.php';

$token = (string) ($_POST['csrf_token'] ?? '');
if (!validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['CSRF validation failed']], JSON_UNESCAPED_UNICODE);
    exit;
}

$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Configuration error']], JSON_UNESCAPED_UNICODE);
    exit;
}

$logsPath = $config['paths']['logs'];
if (!is_string($logsPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Configuration error']], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = Database::getInstance($dbPath);
$product = new Product($db);

$telegram = new TelegramService(
    (string) ($config['telegram']['bot_token'] ?? ''),
    (string) ($config['telegram']['chat_id'] ?? ''),
    $logsPath . '/app.log',
);

$emailConfig = $config['email'] ?? [];
if (!is_array($emailConfig)) {
    $emailConfig = [];
}

$handler = new FormHandler(
    $db,
    $telegram,
    $product,
    $emailConfig,
    $logsPath . '/app.log',
);

$result = $handler->processOrderForm($_POST);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
