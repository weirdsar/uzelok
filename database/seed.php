<?php

declare(strict_types=1);

/**
 * Fill the database with catalog data via the same sync path as cron (mock when API keys are placeholders).
 */

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
    $controller = SyncBootstrap::createSyncController($config, $product, $db, $logFile, 'seed');
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$result = $controller->sync();

echo "Seed completed:\n";
echo '  Status: ' . ($result['status'] ?? '') . "\n";
echo '  Products updated: ' . (string) ($result['updated'] ?? 0) . "\n";
echo '  Products deactivated: ' . (string) ($result['deactivated'] ?? 0) . "\n";
if (($result['errors'] ?? '') !== '') {
    echo '  Errors: ' . (string) $result['errors'] . "\n";
}

exit(($result['status'] ?? '') === 'success' ? 0 : 1);
