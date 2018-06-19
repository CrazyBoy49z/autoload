<?php

class Autoload
{
//**************************************************************************************************************************************************
    public static function routeEvent($eventName)
    {
        global $modx;	//не убирать, используется в include $file;

        switch ($eventName) {
            case 'OnMODXInit':
                define('MODX_ELEMENTS_PATH', MODX_CORE_PATH.'elements/');
                define('MODX_EXT_FOLDER', 'core/ext/');
                define('MODX_EXT_PATH', MODX_CORE_PATH.'ext/');


                spl_autoload_register(function ($class) {
                    if ((strpos($class, '/')) == false) { // что бы не подсунули '../'
                        $name = strtolower($class);
                        $filename = strtolower(MODX_CORE_PATH . 'components/' . $name . '/model/' . $name . '.class.php');
                        if (file_exists($filename))
                            include_once($filename);
                        else {
                            $filename = strtolower(MODX_ELEMENTS_PATH . 'class/' . $name . '.class.php');
                            if (file_exists($filename))
                                include_once($filename);
                        }
                    }
                });

                self::includeFile(MODX_CORE_PATH . 'vendor/autoload.php');

                break;
            case 'OnLoadWebDocument':
                $pageFolder = $modx->context->get('key') . '/' . trim($modx->resource->uri, '/') . '/';
                define('MODX_PAGE_FOLDER', $pageFolder);
                $modx->setPlaceholder('MODX_PAGE_FOLDER', MODX_PAGE_FOLDER);

                define('MODX_PAGE_PATH', MODX_ELEMENTS_PATH . MODX_PAGE_FOLDER);

                $templatename = '';
                $id = $modx->resource->get('template');
                if (!empty($id)) {
                    $oTpl = $modx->getObject('modTemplate', $id);
                    if (!empty($oTpl))
                        $templatename = $oTpl->get('templatename');
                }

                define('MODX_TEMPLATE_NAME', $templatename);
                $modx->setPlaceholder('MODX_TEMPLATE_NAME', MODX_TEMPLATE_NAME);

                define('MODX_TEMPLATE_PATH', MODX_ELEMENTS_PATH . 'tpl/' . MODX_TEMPLATE_NAME . '/');

                self::includeFile(MODX_ELEMENTS_PATH . 'tpl/start.php');
                self::includeFile(MODX_ELEMENTS_PATH . 'tpl/' . $templatename . '/start.php');
                self::includeFile(MODX_PAGE_PATH . 'start.php');

                self::includePlaceholders(MODX_PAGE_FOLDER.'placeholders/');
                self::includePlaceholders('tpl/' . MODX_TEMPLATE_NAME . '/placeholders/');
                self::includePlaceholders('tpl/placeholders/');

                break;
        }

        self::includeFiles(MODX_ELEMENTS_PATH . 'plugins/' . $eventName . '/*.php');
    }
//**************************************************************************************************************************************************
    private static function includePlaceholders($dir){
        global $modx;	//не убирать, используется в include $file;
        $files = glob(MODX_ELEMENTS_PATH.$dir.'*.tpl');
        foreach ($files as $file) {
            $name = basename($file, '.'.pathinfo($file, PATHINFO_EXTENSION));
            if (!isset($modx->placeholders[$name])) {
                $modx->setPlaceholder($name, file_get_contents($file));
            }
        }
        $files = glob(MODX_ELEMENTS_PATH.$dir.'*.php');
        foreach ($files as $file) {
            $name = basename($file, '.'.pathinfo($file, PATHINFO_EXTENSION));
            if (!isset($modx->placeholders[$name])) {
                $modx->setPlaceholder($name, include($file));
            }
        }
    }
//**************************************************************************************************************************************************
    public static function includeFiles($pattern){
        $files = glob($pattern);
        foreach ($files as $file)
            if (substr($file, 0, 1) != '_')
                include($file);
    }
//**************************************************************************************************************************************************
    public static function includeFile($file){
        $output = null;
        if (file_exists($file))
            $output = include $file;
        return $output;
    }
//**************************************************************************************************************************************************

}