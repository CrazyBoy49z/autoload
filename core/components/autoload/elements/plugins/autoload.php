<?php
if ($modx->event->name == 'OnMODXInit')
    include_once(MODX_CORE_PATH.'components/autoload/model/autoload.class.php');

Autoload::routeEvent($modx->event->name);