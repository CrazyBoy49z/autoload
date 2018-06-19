<?php
/** @var xPDOTransport $transport */
/** @var array $options */
/** @var modX $modx */
if ($transport->xpdo) {
    $modx =& $transport->xpdo;

    $dev = MODX_BASE_PATH . 'Extras/autoload/';
    /** @var xPDOCacheManager $cache */
    $cache = $modx->getCacheManager();
    if (file_exists($dev) && $cache) {
        if (!is_link($dev . 'assets/components/autoload')) {
            $cache->deleteTree(
                $dev . 'assets/components/autoload/',
                ['deleteTop' => true, 'skipDirs' => false, 'extensions' => []]
            );
            symlink(MODX_ASSETS_PATH . 'components/autoload/', $dev . 'assets/components/autoload');
        }
        if (!is_link($dev . 'core/components/autoload')) {
            $cache->deleteTree(
                $dev . 'core/components/autoload/',
                ['deleteTop' => true, 'skipDirs' => false, 'extensions' => []]
            );
            symlink(MODX_CORE_PATH . 'components/autoload/', $dev . 'core/components/autoload');
        }
    }
}

return true;