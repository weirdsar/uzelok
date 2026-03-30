<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;
use Uzelok\Core\Service\UserInfographicsSync;
use Uzelok\Core\SyncBootstrap;

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__, 2) . '/config/config.php';

$adminUser = (string) ($config['admin']['username'] ?? 'admin');
$adminPass = (string) ($config['admin']['password'] ?? '');

$authUser = $_SERVER['PHP_AUTH_USER'] ?? '';
$authPass = $_SERVER['PHP_AUTH_PW'] ?? '';

if ($authUser !== $adminUser || !hash_equals($adminPass, $authPass)) {
    header('WWW-Authenticate: Basic realm="Admin"');
    http_response_code(401);
    echo 'Authorization required';
    exit;
}

$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    http_response_code(500);
    echo 'Configuration error';
    exit;
}

$db = Database::getInstance($dbPath);
$product = new Product($db);

$logsPath = (string) ($config['paths']['logs'] ?? dirname(__DIR__, 2) . '/logs');
$logFile = $logsPath . '/sync.log';

try {
    $controller = SyncBootstrap::createSyncController($config, $product, $db, $logFile, 'manual');
} catch (\InvalidArgumentException $e) {
    http_response_code(500);
    echo 'Configuration error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    exit;
}

$syncResult = null;
$infographicsResult = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['run_sync'])) {
    $syncResult = $controller->sync();
    $root = isset($config['paths']['root']) && is_string($config['paths']['root'])
        ? $config['paths']['root']
        : dirname(__DIR__, 2);
    $public = isset($config['paths']['public']) && is_string($config['paths']['public'])
        ? $config['paths']['public']
        : $root . DIRECTORY_SEPARATOR . 'public_html';
    $srcDir = $root . DIRECTORY_SEPARATOR . 'user_content';
    $destDir = $public . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'user-content';
    $infographicsResult = UserInfographicsSync::syncFromUserContent(
        $db->getConnection(),
        $srcDir,
        $destDir
    );
}

$stmt = $db->query('SELECT * FROM sync_log ORDER BY id DESC LIMIT 1', []);
$lastSync = $stmt->fetch();
$activeCount = $product->count(true);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Синхронизация — УЗЕЛОК64</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#0a0a0f] text-[#e8e8f0] p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-orange-500 mb-6">Синхронизация Ozon</h1>

        <?php if ($syncResult !== null) : ?>
            <div class="mb-6 rounded-lg border border-white/10 bg-white/5 p-4">
                <p class="font-mono text-sm">Статус: <span class="text-orange-400"><?= htmlspecialchars((string) $syncResult['status'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span></p>
                <p class="font-mono text-sm">Обновлено: <?= (int) $syncResult['updated'] ?></p>
                <p class="font-mono text-sm">Деактивировано: <?= (int) $syncResult['deactivated'] ?></p>
                <?php if (($syncResult['errors'] ?? '') !== '') : ?>
                    <p class="mt-2 text-red-400 text-sm"><?= htmlspecialchars((string) $syncResult['errors'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (is_array($infographicsResult)) : ?>
            <div class="mb-6 rounded-lg border border-white/10 bg-white/5 p-4">
                <h2 class="text-lg font-semibold mb-2">Инфографика (user_content → сайт)</h2>
                <p class="font-mono text-sm">Скопировано файлов: <?= (int) $infographicsResult['copied'] ?></p>
                <p class="font-mono text-sm">Карточек обновлено в БД: <?= (int) $infographicsResult['db_updated'] ?></p>
                <?php if (($infographicsResult['messages'] ?? []) !== []) : ?>
                    <pre class="mt-2 max-h-48 overflow-y-auto text-xs font-mono text-[#a0a0b8]"><?= htmlspecialchars(implode("\n", $infographicsResult['messages']), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mb-6 rounded-lg border border-white/10 bg-white/5 p-4">
            <h2 class="text-lg font-semibold mb-2">Последняя запись sync_log</h2>
            <?php if (is_array($lastSync) && $lastSync !== []) : ?>
                <pre class="text-xs overflow-x-auto font-mono text-[#a0a0b8]"><?= htmlspecialchars(json_encode($lastSync, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></pre>
            <?php else : ?>
                <p class="text-[#a0a0b8]">Пока нет записей.</p>
            <?php endif; ?>
        </div>

        <p class="mb-4 text-[#a0a0b8]">Активных товаров в БД: <strong class="text-white"><?= $activeCount ?></strong></p>

        <form method="post" class="inline">
            <input type="hidden" name="run_sync" value="1">
            <button type="submit" class="rounded-lg bg-orange-600 px-6 py-3 font-semibold text-white hover:bg-orange-500">
                Синхронизировать сейчас
            </button>
        </form>
    </div>
</body>
</html>
