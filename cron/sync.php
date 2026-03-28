<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;
use Uzelok\Core\SyncBootstrap;

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__) . '/config/config.php';

$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    fwrite(STDERR, "Invalid database path.\n");
    exit(1);
}

$db = Database::getInstance($dbPath);
$product = new Product($db);

$logsPath = (string) ($config['paths']['logs'] ?? dirname(__DIR__) . '/logs');
$logFile = $logsPath . '/sync.log';

try {
    $controller = SyncBootstrap::createSyncController($config, $product, $db, $logFile, 'cron');
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$result = $controller->sync();

$line = sprintf(
    "[%s] sync status=%s updated=%d deactivated=%d errors=%s\n",
    gmdate('Y-m-d H:i:s'),
    $result['status'],
    $result['updated'],
    $result['deactivated'],
    $result['errors']
);
echo $line;

exit($result['status'] === 'success' ? 0 : 1);
