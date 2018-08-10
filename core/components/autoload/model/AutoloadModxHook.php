<?php
class AutoloadModxHook
{
    /** @var modX $modx */
    private $modx;

    private $output;

//**************************************************************************************************************************************************
    function __construct(modX &$modx)
    {
        $this->modx = & $modx;

        $this->output = '';
    }

//**************************************************************************************************************************************************
    private function error($msg)
    {
        $this->output .=  '<span class="error">' . $msg . '</span><br>' . "\n";
    }

//**************************************************************************************************************************************************
    private function warning($msg)
    {
        $this->output .=  '<span class="warn">' . $msg . '</span><br>' . "\n";
    }

//**************************************************************************************************************************************************
    private function info($msg)
    {
        $this->output .=  '<span class="info">' . $msg . '</span><br>' . "\n";
    }
//**************************************************************************************************************************************************
    private function dir2events($dir, $vendor, &$events, &$plugins)
    {
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
                        $this->error('ERROR! ' . "Empty property 'events'");
                        $this->error("File: " . $dir . $file . '/plugins.json');
                        $this->error("Node " . $k);
                    }
                }
            }
        }
    }

//**************************************************************************************************************************************************
    public function update()
    {
        $this->info('autoload update start');
        //install/uninstall
        $aExist = [];

        $dir = MODX_CORE_PATH.'elements/etc/autoload/uninstall/';
        if (file_exists($dir)) {
            $files = scandir($dir);
            foreach ($files as $subdir) {
                if (is_dir($dir . $subdir) && $subdir != '.' && $subdir != '..') {
                    $files2 = scandir($dir . $subdir);
                    foreach ($files2 as $subdir2) {
                        if (is_dir($dir . $subdir . '/' . $subdir2) && $subdir2 != '.' && $subdir2 != '..') {
                            $aExist[$subdir . '-' . $subdir2] = $dir . $subdir . '/' . $subdir2 . '/';
                        }
                    }
                }
            }
        }

        $dir = MODX_CORE_PATH.'vendor/';
        if (file_exists($dir)) {
            $files = scandir($dir);
            foreach ($files as $subdir) {
                if (is_dir($dir . $subdir) && $subdir != '.' && $subdir != '..' && $subdir != 'composer') {
                    $files2 = scandir($dir . $subdir);
                    foreach ($files2 as $subdir2) {
                        if (is_dir($dir . $subdir . '/' . $subdir2) && file_exists($dir . $subdir . '/' . $subdir2 . '/modx.json')) {
                            if (isset($aExist[$subdir . '-' . $subdir2]))
                                unset($aExist[$subdir . '-' . $subdir2]);
                            else
                                $this->install($dir . $subdir . '/' . $subdir2 . '/', $subdir . '-' . $subdir2);

                        }
                    }
                }
            }
        }

        foreach ($aExist as $namespace => $dir)
            $this->uninstall($dir, $namespace);


        //plugins
        $events = [];
        $plugins = [];

        $fileEvents = MODX_CORE_PATH.'events.json';
        $filePlugins = MODX_CORE_PATH.'plugins.json';
        if (file_exists($fileEvents))
            unlink($fileEvents);
        if (file_exists($filePlugins))
            unlink($filePlugins);

        $this->dir2events(MODX_CORE_PATH.'components/', 'components', $events, $plugins);

        $dir = MODX_CORE_PATH.'vendor/';
        if (file_exists($dir)) {
            $files = scandir($dir);
            foreach ($files as $subdir) {
                if (is_dir($dir . $subdir)) {
                    $this->dir2events($dir . $subdir, $subdir, $events, $plugins);
                }
            }
        }

        foreach ($events as $event) {
            ksort($event);
        }

        file_put_contents($fileEvents, json_encode($events));
        $this->warning("File created successfully: " . $fileEvents);
        file_put_contents($filePlugins, json_encode($plugins));
        $this->warning("File created successfully: " . $filePlugins);

        $obj = $this->modx->getObject('modPlugin',  ['name' => 'gjpbw_routeEvent']);
        $id = $obj->id;
        $collection = $this->modx->getCollection('modPluginEvent',  ['pluginid' => $id]);
        foreach ($collection as $obj) {
            $event = $obj->get('event');
            if ($event !== 'OnMODXInit' && !isset($events[$event])) {
                $obj->remove();
                $this->warning("Remove event: " . $event);
            } else
                unset($events[$event]);
        }

        foreach ($events as $event => $v){
            if ($event !== 'OnMODXInit') {
                $obj = $this->modx->newObject('modPluginEvent');
                $obj->pluginid = $id;
                $obj->event = $event;
                $obj->priority = 50;
                $obj->save();
                $this->warning("Create event: " . $event);
            }
        }

        $this->info('autoload update stop');

        return $this->output;

    }

//**************************************************************************************************************************************************
    private function install($dir, $namespace)
    {
        $modx = $this->modx;    //не убирать, используется в include
        $properties = json_decode(file_get_contents($dir . '/modx.json'), true);

        $data = ['name' => $namespace];
        if (!empty($properties['core_path']))
            $data['core_path'] = $properties['core_path'];
        if (!empty($properties['assets_path']))
            $data['assets_path'] = $properties['assets_path'];

        $obj = $this->modx->getObject('modNamespace', ['name' => $namespace]);
        if (empty($obj)) {
            $this->warning('create namespace ' . $namespace);
            $response = $this->modx->runProcessor('workspace/namespace/create', $data);
            if ($response->isError())
                $this->error('ERROR! ' . $response->getMessage());
        }

        if (!empty($properties['category'])) {
            $obj = $this->modx->getObject('modCategory', ['category' => $properties['category']]);
            if (empty($obj)) {
                $this->warning('create category ' . $properties['category']);
                $response = $this->modx->runProcessor('element/category/create', ['category' => $properties['category']]);
                if ($response->isError())
                    $this->error('ERROR! ' . $response->getMessage());
            }
        }

        if (!empty($properties['install'])) {
            if (!empty($properties['install']['resources'])) {
                foreach ($properties['install']['resources'] as $k => $v) {
                    $obj = $this->modx->getObject('modResource', ['uri' => $k]);
                    if (empty($obj)) {

                        if ($v['isfolder'])
                            $k = rtrim($k, '/') . '/';

                        $v['uri'] = $k;
                        $v['uri_override'] = 1;
                        if (!empty($v['parent'])) {
                            $obj = $this->modx->getObject('modResource', ['uri' => $v['parent'] . '/']);
                            if (!empty($obj))
                                $v['parent'] = $obj->get('id');
                        }
                        $this->warning('create resource ' . $k);
                        $response = $this->modx->runProcessor('resource/create', $v);
                        if ($response->isError())
                            $this->error('ERROR! ' . $response->getMessage());
                    }
                    else
                        $this->error('ERROR! Resource already exist, uri = ' . $k);
                }
            }

            if (!empty($properties['install']['systemSetting'])) {
                foreach ($properties['install']['systemSetting'] as $k => $v) {
                    $obj = $this->modx->getObject('modSystemSetting', $k);
                    if (empty($obj)) {
                        $this->warning('create systemSetting ' . $k );
                        $response = $this->modx->runProcessor('system/settings/create', ['key' => $k, 'namespace' => $namespace, 'value' => $v]);
                        if ($response->isError())
                            $this->error('ERROR! ' . $response->getMessage());
                    } else {
                        $this->warning('update systemSetting ' . $k );
                        $response = $this->modx->runProcessor('system/settings/update', ['key' => $k, 'namespace' => $namespace, 'value' => $v]);
                        if ($response->isError())
                            $this->error('ERROR! ' . $response->getMessage());
                    }
                }
            }
        }

        $file = $dir . 'mysql.schema.xml';
        if (file_exists($file)) {
            /** @var xPDOManager $manager */
            $manager = $this->modx->getManager();
            /** @var xPDOGenerator $generator */
            $generator = $manager->getGenerator();
            $schemaDir = MODX_CORE_PATH.'elements/etc/om/';
            if (!file_exists($schemaDir.$namespace))
                mkdir($schemaDir.$namespace, 0744, true);

            $generator->parseSchema($file, $schemaDir);
            $this->warning('Model updated');

            $this->modx->addPackage($namespace, $schemaDir);

            $objects = [];
            $schema = new SimpleXMLElement($file, 0, true);
            if (isset($schema->object)) {
                foreach ($schema->object as $obj) {
                    $objects[] = (string)$obj['class'];
                }
            }
            unset($schema);
            foreach ($objects as $class) {
                $table = $this->modx->getTableName($class);
                $sql = "SHOW TABLES LIKE '" . trim($table, '`') . "'";
                $stmt = $this->modx->prepare($sql);
                $newTable = true;
                if ($stmt->execute() && $stmt->fetchAll()) {
                    $newTable = false;
                }
                // If the table is just created
                if ($newTable) {
                    $manager->createObjectContainer($class);
                } else {
                    // If the table exists
                    // 1. Operate with tables
                    $tableFields = [];
                    $c = $this->modx->prepare("SHOW COLUMNS IN {$this->modx->getTableName($class)}");
                    $c->execute();
                    while ($cl = $c->fetch(PDO::FETCH_ASSOC)) {
                        $tableFields[$cl['Field']] = $cl['Field'];
                    }
                    foreach ($this->modx->getFields($class) as $field => $v) {
                        if (in_array($field, $tableFields)) {
                            unset($tableFields[$field]);
                            $manager->alterField($class, $field);
                        } else {
                            $manager->addField($class, $field);
                        }
                    }
                    foreach ($tableFields as $field) {
                        $manager->removeField($class, $field);
                    }
                    // 2. Operate with indexes
                    $indexes = [];
                    $c = $this->modx->prepare("SHOW INDEX FROM {$this->modx->getTableName($class)}");
                    $c->execute();
                    while ($row = $c->fetch(PDO::FETCH_ASSOC)) {
                        $name = $row['Key_name'];
                        if (!isset($indexes[$name])) {
                            $indexes[$name] = [$row['Column_name']];
                        } else {
                            $indexes[$name][] = $row['Column_name'];
                        }
                    }
                    foreach ($indexes as $name => $values) {
                        sort($values);
                        $indexes[$name] = implode(':', $values);
                    }
                    $map = $this->modx->getIndexMeta($class);
                    // Remove old indexes
                    foreach ($indexes as $key => $index) {
                        if (!isset($map[$key])) {
                            if ($manager->removeIndex($class, $key)) {
                                $this->warning("Removed index \"{$key}\" of the table \"{$class}\"");
                            }
                        }
                    }
                    // Add or alter existing
                    foreach ($map as $key => $index) {
                        ksort($index['columns']);
                        $index = implode(':', array_keys($index['columns']));
                        if (!isset($indexes[$key])) {
                            if ($manager->addIndex($class, $key)) {
                                $this->warning("Added index \"{$key}\" in the table \"{$class}\"");
                            }
                        } else {
                            if ($index != $indexes[$key]) {
                                if ($manager->removeIndex($class, $key) && $manager->addIndex($class, $key)) {
                                    $this->warning("Updated index \"{$key}\" of the table \"{$class}\"");
                                }
                            }
                        }
                    }
                }
            }
        }

        $file = $dir . 'install.php';
        if (file_exists($file)) {
            include $file;
        }

        $destDir = MODX_CORE_PATH.'elements/etc/autoload/uninstall/' . $namespace;

        if (!file_exists($destDir))
            mkdir($destDir, 0744, true);

        if (file_exists($dir . 'uninstall.php'))
            copy($dir . 'uninstall.php',  $destDir . '/uninstall.php');

        if (file_exists($dir . 'modx.json'))
            copy($dir . 'modx.json',  $destDir . '/modx.json');
    }
//**************************************************************************************************************************************************
    private  function uninstall($dir, $namespace)
    {
        $modx = $this->modx;    //не убирать, используется в include
        $properties = json_decode(file_get_contents($dir . 'modx.json'), true);

        if (!empty($properties['category'])) {
            $obj = $this->modx->getObject('modCategory', ['category' => $properties['category']]);
            if (!empty($obj)) {
                $this->warning('remove category ' . $properties['category']);
                $response = $this->modx->runProcessor('element/category/remove', ['id' => $obj->get('id')]);
                if($response->isError())
                    $this->error('ERROR! ' . $response->getMessage());
            }
        }

        $obj = $this->modx->getObject('modNamespace', ['name' => $namespace]);
        if (!empty($obj)) {
            $this->warning('remove namespace ' . $namespace);
            $response = $this->modx->runProcessor('workspace/namespace/remove', ['name' => $namespace]);
            if ($response->isError())
                $this->error('ERROR! ' . $response->getMessage());
        }

        if (!empty($properties['uninstall'])){
            if (!empty($properties['uninstall']['resources'])) {
                foreach ($properties['uninstall']['resources'] as $uri) {
                    $obj = $this->modx->getObject('modResource', ['uri' => $uri]);
                    if (!empty($obj)) {
                        $this->warning('remove resource ' . $uri);
                        $response = $this->modx->runProcessor('resource/delete', ['id' => $obj->get('id')]);
                        if ($response->isError())
                            $this->error('ERROR! ' . $response->getMessage());
                    }
                }
            }

            if (!empty($properties['uninstall']['systemSetting'])) {
                foreach ($properties['uninstall']['systemSetting'] as $k => $v) {
                    $obj = $this->modx->getObject('modSystemSetting', $k);
                    if (empty($obj)) {
                        $this->error('ERROR! ' . 'Not found systemSetting ' . $k );
                    } else {
                        $this->warning('update systemSetting ' . $k );
                        $response = $this->modx->runProcessor('system/settings/update', ['key' => $k, 'namespace' => 'uninstall', 'value' => $v]);
                        if ($response->isError())
                            $this->error('ERROR! ' . $response->getMessage());
                    }
                }
            }
        }

        $file = $dir . 'uninstall.php';
        if (file_exists($file)){
            include $file;
            unlink($file);
        }

        unlink($dir . 'modx.json');
        rmdir($dir);

    }

}