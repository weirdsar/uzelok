<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;
use Uzelok\Core\Service\UserInfographicsSync;
use Uzelok\Core\SyncBootstrap;

use function Uzelok\Core\logLine;

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

$root = isset($config['paths']['root']) && is_string($config['paths']['root'])
    ? $config['paths']['root']
    : dirname(__DIR__);
$public = isset($config['paths']['public']) && is_string($config['paths']['public'])
    ? $config['paths']['public']
    : $root . DIRECTORY_SEPARATOR . 'public_html';
$srcDir = $root . DIRECTORY_SEPARATOR . 'user_content';
$destDir = $public . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'user-content';

$inf = UserInfographicsSync::syncFromUserContent($db->getConnection(), $srcDir, $destDir);
foreach ($inf['messages'] as $m) {
    logLine('INFO', 'Infographics: ' . $m, $logFile);
}
logLine(
    'INFO',
    sprintf('Infographics sync: copied=%d, db_updated=%d', $inf['copied'], $inf['db_updated']),
    $logFile
);

$line = sprintf(
    "[%s] sync status=%s updated=%d deactivated=%d errors=%s\n",
    gmdate('Y-m-d H:i:s'),
    $result['status'],
    $result['updated'],
    $result['deactivated'],
    $result['errors']
);
echo $line;
echo sprintf(
    "[%s] infographics copied=%d db_updated=%d\n",
    gmdate('Y-m-d H:i:s'),
    $inf['copied'],
    $inf['db_updated']
);

exit($result['status'] === 'success' ? 0 : 1);
