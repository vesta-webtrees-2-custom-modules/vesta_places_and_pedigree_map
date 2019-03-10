<?php

use Composer\Autoload\ClassLoader;

$loader = new ClassLoader();
$loader->addPsr4('Cissee\\Webtrees\\Module\\PPM\\', __DIR__);
$loader->register();
