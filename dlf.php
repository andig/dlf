<?php

namespace Dlf;

use Symfony\Component\Console\Application;

require_once('./vendor/autoload.php');
require_once('credentials.php');

define('CATALOG_FILE', 'catalog.json');

$application = new Application();
$application->add(new UpdateCommand());
$application->add(new SearchCommand());
$application->run();
