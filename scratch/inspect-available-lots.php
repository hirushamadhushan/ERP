<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$availableLots = DB::table('product_lots')
    ->join('product_stock_items', 'product_stock_items.id', '=', 'product_lots.product_stock_item_id')
    ->join('inventory_movements', 'inventory_movements.product_lot_id', '=', 'product_lots.id')
    ->join('locations', 'locations.id', '=', 'inventory_movements.location_id')
    ->selectRaw('product_lots.id, product_lots.lot_number, product_stock_items.product_id, locations.id as location_id, SUM(inventory_movements.quantity_delta) as available')
    ->groupBy('product_lots.id', 'product_lots.lot_number', 'product_stock_items.product_id', 'locations.id')
    ->havingRaw('SUM(inventory_movements.quantity_delta) > 0')
    ->get()
    ->groupBy('product_id');

echo json_encode($availableLots, JSON_PRETTY_PRINT);

