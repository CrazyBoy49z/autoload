<?php

class Autoload
{
    /** @var array */
    protected $events;

//**************************************************************************************************************************************************
    function __construct()
    {
        $file = MODX_CORE_PATH.'events.json';
        if (!file_exists($file))
            $this->events = self::update();
        else
            $this->events = json_decode(file_get_contents($file), true);
    }

//**************************************************************************************************************************************************
    private static function dir2events($dir, $vendor, &$events, &$plugins)
    {
        global $modx;
        $files = scandir($dir);
        foreach ($files as $file) {
            if (is_dir($dir . $file) && file_exists($dir . $file . '/plugins.json')) {
                $packagePlugins = json_decode(file_get_contents($dir . $file . '/plugins.json'), true);
                foreach ($packagePlugins as $k => $v) {
                    $packageEvents = $v['events'];
                    if (!empty($packageEvents)) {
                        $packageEvents = explode(',', $packageEvents);
                        foreach ($packageEvents as $event) {
                            $priority = (string)$v['priority'];
                            $method = $v['method'];
                            $key = $priority . '.' . $vendor . '.' . $file . '.' . $k;

                            if (!isset($plugins[$key]))
                                $plugins[$key] = [];
                            $plugins[$key][$event] = $method;


                            if (!isset($events[$event]))
                                $events[$event] = [];
                            $events[$event][$key] = $method;
                        }
                    }
                    else {
                        $modx->log(1, "Empty property 'events'");
                        $modx->log(1, "File: " . $dir . $file . '/plugins.json');
                        $modx->log(1, "Node " . $k);
                    }
                }
            }
        }
    }

//**************************************************************************************************************************************************
    public static function update()
    {
        global $modx;

        $events = [];
        $plugins = [];

        $fileEvents = MODX_CORE_PATH.'events.json';
        $filePlugins = MODX_CORE_PATH.'plugins.json';
        if (file_exists($fileEvents))
            unlink($fileEvents);
        if (file_exists($filePlugins))
            unlink($filePlugins);

        self::dir2events(MODX_CORE_PATH.'components/', 'components', $events, $plugins);

        $dir = MODX_CORE_PATH.'vendor/';
        if (file_exists($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if (is_dir($dir . $file)) {
                    self::dir2events($dir . $file, $file, $events, $plugins);
                }
            }
        }

        foreach ($events as $event) {
            ksort($event);
        }

        file_put_contents($fileEvents, json_encode($events));
        $modx->log(2, "File created successfully: " . $fileEvents);
        file_put_contents($filePlugins, json_encode($plugins));
        $modx->log(2, "File created successfully: " . $filePlugins);

        $obj = $modx->getObject('modPlugin',  ['name' => 'gjpbw_routeEvent']);
        $id = $obj->id;
        $collection = $modx->getCollection('modPluginEvent',  ['pluginid' => $id]);
        foreach ($collection as $obj) {
            $event = $obj->get('event');
            if (!array_key_exists($event, $events) && $event !== 'OnMODXInit') {
                $obj->remove();
                $modx->log(2, "Remove event: " . $event);
            } else
                unset($events[$event]);
        }

        foreach ($events as $event => $v){
            if ($event !== 'OnMODXInit') {
                $obj = $modx->newObject('modPluginEvent');
                $obj->pluginid = $id;
                $obj->event = $event;
                $obj->priority = 50;
                $obj->save();
                $modx->log(2, "Create event: " . $event);
            }
        }

         return $events;
     }
//**************************************************************************************************************************************************
        public static function startPage()
    {
        global $modx;
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

        self::includePlaceholders(MODX_PAGE_FOLDER . 'placeholders/');
        self::includePlaceholders('tpl/' . MODX_TEMPLATE_NAME . '/placeholders/');
        self::includePlaceholders('tpl/placeholders/');
    }
//**************************************************************************************************************************************************
    public function routeEvent($eventName)
    {
        global $modx;	//не убирать, используется в include $file;

        if ($eventName == 'OnMODXInit') {
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
        }

        if (!empty($this->events[$eventName]))
        foreach ($this->events[$eventName] as $event){
            $method = trim($event['method']);
            $a = explode('::', $method);
            if (is_array($a) && count($a) == 2)
                eval ($method .  '();');
            elseif (substr($event,0 , 2) == '[[' &&  substr($event,-2) == ']]')
                $modx->runSnippet(substr($event,2 , -2));
        }

        self::includeFile(MODX_ELEMENTS_PATH . 'plugins/' . $eventName . '.php');
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