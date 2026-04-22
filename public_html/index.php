<?php

declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($requestPath === false || $requestPath === '') {
    $requestPath = '/';
}
if (preg_match('#^/(config|core|database|logs|cron|vendor)(/|$)#i', $requestPath)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use Uzelok\Core\Brand;
use Uzelok\Core\Database;
use Uzelok\Core\Model\Product;

/** @var array<string, mixed> $config */
$config = require __DIR__ . '/../config/config.php';

$dbPath = $config['paths']['database'];
if (!is_string($dbPath)) {
    http_response_code(500);
    echo 'Configuration error';
    exit;
}

$db = Database::getInstance($dbPath);
$productModel = new Product($db);

$pathLower = strtolower($requestPath);
$isFrontControllerPath = $requestPath === '/' || $pathLower === '/index.php';

$page = $isFrontControllerPath
    ? (isset($_GET['page']) ? (string) $_GET['page'] : 'home')
    : '__invalid__';

session_start();
$csrfToken = \Uzelok\Core\generateCsrfToken();

$brands = Brand::cases();

$brandFilter = null;
$products = [];
/** @var array<string, mixed>|null $productDetail */
$productDetail = null;
$template = 'home';
$pageTitle = (string) ($config['app_name'] ?? 'УЗЕЛОК64');
$pageDescription = '';
/** @var array<string, int> */
$catalogTabCounts = [];

switch ($page) {
    case 'product':
        $rawId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($rawId !== false && $rawId !== null && $rawId > 0) {
            $row = $productModel->findById((int) $rawId);
            if ($row !== null && (int) ($row['is_active'] ?? 0) === 1) {
                $productDetail = $row;
            }
        }
        if ($productDetail === null) {
            http_response_code(404);
            $brandFilter = null;
            $products = [];
            $template = '404';
            $page = '404';
            $pageTitle = 'Страница не найдена — УЗЕЛОК64';
            $pageDescription = '';
        } else {
            $brandFilter = null;
            $products = [];
            $template = 'product';
            $t = (string) ($productDetail['title'] ?? 'Товар');
            $pageTitle = $t . ' — купить с доставкой | УЗЕЛОК64';
            $articleMeta = preg_replace('/\s+/u', ' ', trim(\Uzelok\Core\productSeoArticlePlain($productDetail)));
            if (mb_strlen($articleMeta) >= 100) {
                $pageDescription = mb_strlen($articleMeta) > 165 ? mb_substr($articleMeta, 0, 162) . '…' : $articleMeta;
            } else {
                $descFull = trim((string) ($productDetail['description'] ?? ''));
                if ($descFull !== '') {
                    $pageDescription = mb_strlen($descFull) > 160 ? mb_substr($descFull, 0, 157) . '…' : $descFull;
                } else {
                    $pageDescription = $t . ' — УЗЕЛОК64: хозтовары, автоаксессуары, туризм. Заказ напрямую выгоднее Ozon.';
                }
            }
        }
        break;
    case 'home':
        $brandFilter = isset($_GET['brand']) ? Brand::tryFrom((string) $_GET['brand']) : null;
        $products = $brandFilter !== null
            ? $productModel->findByBrand($brandFilter)
            : $productModel->findAll();
        $catalogTabCounts = ['all' => $productModel->count()];
        foreach ($brands as $b) {
            $catalogTabCounts[$b->value] = $productModel->countByBrand($b);
        }
        $template = 'home';
        $pageTitle = 'УЗЕЛОК64 — Хозтовары, автоаксессуары, снаряжение';
        $pageDescription = 'Интернет-магазин текстильных изделий: хозяйственные сумки БАТЯ, автоаксессуары БУЙ, снаряжение для охоты и рыбалки ВОЛНА. Дешевле, чем на Ozon. Доставка по РФ.';
        break;
    case 'workshop':
        $brandFilter = null;
        $products = [];
        $template = 'workshop';
        $pageTitle = 'Мастерская — УЗЕЛОК64';
        $pageDescription = 'Инженерно-швейная мастерская в Саратове. Проектирование текстильных изделий, создание лекал, пошив эталонных образцов. От идеи до серийного производства.';
        break;
    case 'contacts':
        $brandFilter = null;
        $products = [];
        $template = 'contacts';
        $pageTitle = 'Контакты — УЗЕЛОК64';
        $pageDescription = 'Контакты УЗЕЛОК64. г. Саратов, ул. Киселёва, д. 18Б (юридический и фактический адрес). Телефон +7 (929) 770-00-84, email ananev-dm@mail.ru, форма обратной связи. Доставка по всей России.';
        break;
    default:
        http_response_code(404);
        $brandFilter = null;
        $products = [];
        $template = '404';
        $page = '404';
        $pageTitle = 'Страница не найдена — УЗЕЛОК64';
        $pageDescription = '';
        break;
}

require __DIR__ . '/../templates/layout.php';
