<?php

declare(strict_types=1);

namespace Uzelok\Core;

use Uzelok\Core\Controller\SyncController;
use Uzelok\Core\Model\Product;
use Uzelok\Core\Service\OzonEnvParser;
use Uzelok\Core\Service\OzonService;

final class SyncBootstrap
{
    public static function createSyncController(
        array $config,
        Product $product,
        Database $db,
        string $logFile,
        string $triggerType,
    ): SyncController {
        $ozonCfg = $config['ozon'] ?? [];
        if (!is_array($ozonCfg)) {
            $ozonCfg = [];
        }

        $skus = $ozonCfg['skus'] ?? [];
        $skuMap = $ozonCfg['sku_brand_map'] ?? [];
        if (!is_array($skus)) {
            $skus = [];
        }
        if (!is_array($skuMap)) {
            $skuMap = [];
        }

        $root = isset($config['paths']['root']) && is_string($config['paths']['root'])
            ? $config['paths']['root']
            : dirname(__DIR__);
        $envPath = $root . DIRECTORY_SEPARATOR . '.ozon.env';
        $accounts = OzonEnvParser::tryLoadAccounts($envPath);
        $ozonAccounts = $accounts !== [] ? $accounts : null;

        if ($ozonAccounts === null && $skus === []) {
            throw new \InvalidArgumentException('Ozon: empty skus and no readable .ozon.env with accounts');
        }

        $ozon = new OzonService(
            (string) ($ozonCfg['client_id'] ?? ''),
            (string) ($ozonCfg['api_key'] ?? ''),
            isset($ozonCfg['base_url']) ? (string) $ozonCfg['base_url'] : null,
        );

        $baseUrl = isset($ozonCfg['base_url']) ? (string) $ozonCfg['base_url'] : null;

        $public = isset($config['paths']['public']) && is_string($config['paths']['public'])
            ? $config['paths']['public']
            : $root . DIRECTORY_SEPARATOR . 'public_html';
        $productImagesDirectory = $public . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'products';
        if (!is_dir($productImagesDirectory)) {
            @mkdir($productImagesDirectory, 0755, true);
        }

        return new SyncController(
            $ozon,
            $product,
            $db,
            $logFile,
            $skus,
            $skuMap,
            $triggerType,
            $ozonAccounts,
            $baseUrl,
            $productImagesDirectory,
        );
    }
}
