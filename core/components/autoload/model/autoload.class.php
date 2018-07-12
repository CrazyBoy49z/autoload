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
            $this->events = json_decode(file_get_contents($file));
    }

//**************************************************************************************************************************************************
    private static function dir2events($dir, $vendor, &$events, &$plugins)
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if (is_dir($dir . $file) && file_exists($dir . $file . '/plugins.json')) {
                $data = json_decode(file_get_contents($dir . $file . '/plugins.json'));
                foreach ($data as $k => $v) {
                    $events = $v['events'];
                    $events = explode(',', $events);
                    foreach ($events as $event) {
                        $priority = (string)$v['priority'];
                        $method = $v['method'];
                        $key = $priority . '.' . $vendor . '.' . $file . '.' . $k;

                        if (!is_array($plugins[$key]))
                            $plugins[$key] = [];
                        $plugins[$key][$event] = $method;


                        if (!is_array($events[$event]))
                            $events[$event] = [];
                        $events[$event][$key] = $method;
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

        unlink(MODX_CORE_PATH.'_events_flip.json');
        unlink(MODX_CORE_PATH.'events.json');

        self::dir2events(MODX_CORE_PATH.'components/', 'components', $events, $plugins);

        $dir = MODX_CORE_PATH.'vendor/';
        $files = scandir($dir);
        foreach ($files as $file) {
            if (is_dir($dir . $file)){
                self::dir2events($dir . $file, $file, $events, $plugins);
            }
        }

        foreach ($events as $event) {
            ksort($event);
        }

        $obj = $modx->getObject('modPlugin',  ['name' => 'autoload.routeEvent']);
        $id = $obj->id;
        $collection = $modx->getCollection('modPluginEvent',  ['pluginid' => $id]);
        foreach ($collection as $obj) {
            $event = $obj->get('event');
            if (!array_key_exists($event, $events) && $event !== 'OnMODXInit')
                $obj->remove();
            else
                unset($events[$event]);

        foreach ($events as $event){
            if ($event !== 'OnMODXInit') {
                $obj = $modx->newObject('modPluginEvent', ['pluginid' => $id, 'event' => $event]);
                $obj->save();
            }
        }

    }

        file_put_contents(MODX_CORE_PATH.'_events_flip.json', json_encode($plugins));
        file_put_contents(MODX_CORE_PATH.'events.json', json_encode($events));
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

        //self::includeFiles(MODX_ELEMENTS_PATH . 'plugins/' . $eventName . '/*.php');
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