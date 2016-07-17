<?php

namespace Dlf;

use Symfony\Component\Console\Application;

require_once('./vendor/autoload.php');
require_once('credentials.php');
require_once('defines.php');

$application = new Application();
$application->add(new UpdateCommand());
$application->add(new SearchCommand());
$application->add(new AuthorizeCommand());
$application->run();
