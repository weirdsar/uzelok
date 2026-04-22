<?php
/** @var string $pageTitle */
/** @var string $pageDescription */
/** @var string $page */
/** @var string $template */
/** @var array<int, array<string, mixed>> $products */
/** @var array<string, mixed> $config */
/** @var string $csrfToken */
/** @var array<int, \Uzelok\Core\Brand> $brands */
/** @var \Uzelok\Core\Brand|null $brandFilter */
/** @var array<string, int> $catalogTabCounts */
/** @var array<string, mixed>|null $productDetail */
$baseUrl = rtrim((string) ($config['app_url'] ?? 'https://uzelok64.ru'), '/');
$appUrl = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$fullUrl = htmlspecialchars($baseUrl . $requestUri, ENT_QUOTES, 'UTF-8');
$desc = (string) ($pageDescription ?? '');
$ogDesc = htmlspecialchars($desc !== '' ? $desc : 'БАТЯ • БУЙ • ВОЛНА — хозтовары, автоаксессуары, снаряжение для туризма. Прямые цены с uzelok64.ru', ENT_QUOTES, 'UTF-8');
$ogTitle = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ru" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#0a0a0f">
    <link rel="canonical" href="<?= $fullUrl ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $ogTitle ?>">
    <meta property="og:description" content="<?= $ogDesc ?>">
    <meta property="og:url" content="<?= $fullUrl ?>">
    <meta property="og:image" content="<?= $appUrl ?>/assets/images/hero-bg.jpg">
    <meta property="og:locale" content="ru_RU">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Ctext y='24' font-size='24'%3E%E2%9A%A1%3C/text%3E%3C/svg%3E">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'uz-dark': { DEFAULT: '#0a0a0f', secondary: '#12121a', tertiary: '#1a1a2e' },
                        'uz-surface': '#1e1e2e',
                        'uz-border': '#2a2a3e',
                        'uz-text': { DEFAULT: '#e8e8f0', secondary: '#a0a0b8', muted: '#6b6b80' },
                        'uz-accent': { DEFAULT: '#f97316', hover: '#ea580c' },
                        'brand-batya': '#d97706',
                        'brand-buy': '#2563eb',
                        'brand-volna': '#059669',
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        .industrial-grid { background-image: linear-gradient(rgba(42,42,62,0.35) 1px, transparent 1px), linear-gradient(90deg, rgba(42,42,62,0.35) 1px, transparent 1px); background-size: 32px 32px; }
        dialog::backdrop { background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }
        .fade-in-up { animation: fadeInUp 0.6s ease-out forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <?php
    /** Счётчик по умолчанию — как в metrika.md (108717789). В config.php можно задать yandex_metrika_id или 0, чтобы отключить. */
    $ymId = (int) ($config['yandex_metrika_id'] ?? 108717789);
    if ($ymId > 0) :
        ?>
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=<?= $ymId ?>', 'ym');

        ym(<?= $ymId ?>, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/<?= $ymId ?>" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
    <?php endif; ?>
</head>
<body class="bg-[#0a0a0f] text-[#e8e8f0] font-sans antialiased industrial-grid min-h-screen">
    <div class="scroll-progress-track" aria-hidden="true">
        <div id="scroll-progress-fill" class="scroll-progress-fill"></div>
    </div>
    <div id="toast-container" class="toast-container" role="alert" aria-live="polite" aria-atomic="true"></div>
    <?php require __DIR__ . '/header.php'; ?>
    <main class="relative z-10 pt-24">
        <?php require __DIR__ . '/pages/' . $template . '.php'; ?>
    </main>
    <?php require __DIR__ . '/footer.php'; ?>
    <?php require __DIR__ . '/components/order-modal.php'; ?>
    <script src="/assets/js/app.js" defer></script>
</body>
</html>
