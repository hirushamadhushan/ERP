<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
    $result = $kernel->call($command);
    echo $kernel->output();

    if ($result !== 0) {
        exit($result);
    }
}
