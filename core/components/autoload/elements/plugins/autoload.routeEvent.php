<?php
global $gjpbw;
if ($modx->event->name == 'OnMODXInit') {
   include_once(MODX_CORE_PATH . 'components/autoload/model/autoload.class.php');
   $gjpbw = [];
   $gjpbw['Autoload'] = new Autoload();
}

$gjpbw['Autoload']->routeEvent($modx->event->name);