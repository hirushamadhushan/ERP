<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$services = \App\Models\Service::all();
echo "Service count: " . $services->count() . "\n";
foreach ($services as $s) {
    echo "- ID: {$s->id}, Code: {$s->code}, Name: {$s->name}, Rate: {$s->unit_rate}, Active: " . ($s->is_active ? 'Yes' : 'No') . "\n";
}

