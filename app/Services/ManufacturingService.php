<?php
namespace App\Services;

use App\Models\{BillOfMaterial, InventoryMovement, InventoryTransaction, ManufacturingOrder, Product, ProductLot, ProductSerialNumber, ProductStockItem};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManufacturingService
{
    public function createDraft(array $data, int $userId): ManufacturingOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $this->validateLocation((int) $data['location_id']);
            $output = Product::with('unit')->findOrFail($data['product_id']);
            $this->validateOutput($output, (string) $data['quantity']);
            $bom = BillOfMaterial::with(['items.product.unit','items.productVariant'])->whereKey($data['bill_of_material_id'])->where('is_active', true)->first();
            if (! $bom || $bom->product_id !== $output->id) throw ValidationException::withMessages(['bill_of_material_id' => 'Select an active recipe for the finished product.']);
            $scale = bcdiv((string) $data['quantity'], (string) $bom->output_quantity, 8);
            $components = $bom->items->map(function ($item) use ($scale) {
                $this->validateComponent($item->product, $item->productVariant);
                $quantity = bcmul((string) $item->quantity, $scale, 4);
                if (! $item->product->unit?->allow_decimal && bccomp($quantity, (string) floor((float) $quantity), 4) !== 0) throw ValidationException::withMessages(['quantity' => $item->product->name.' requires a whole-number quantity. Choose a compatible output quantity.']);
                return ['product' => $item->product, 'variant' => $item->productVariant, 'quantity' => $quantity];
            });
            $materialCost = $components->reduce(fn ($sum, $line) => bcadd($sum, bcmul((string) ($line['variant']?->purchase_price ?? $line['product']->purchase_price), $line['quantity'], 4), 4), '0');
            $expenses = collect($data['expenses'] ?? [])->filter(fn ($e) => filled($e['name'] ?? null) || isset($e['amount']));
            $expenseCost = $expenses->reduce(fn ($sum, $e) => bcadd($sum, (string) $e['amount'], 4), '0');
            $totalCost = bcadd($materialCost, $expenseCost, 4);
            $order = ManufacturingOrder::create(['number' => 'TMP-'.bin2hex(random_bytes(8)), 'product_id' => $output->id, 'bill_of_material_id' => $bom->id, 'location_id' => $data['location_id'], 'quantity' => $data['quantity'], 'material_cost' => $materialCost, 'expense_cost' => $expenseCost, 'total_cost' => $totalCost, 'unit_cost' => bcdiv($totalCost, (string) $data['quantity'], 4), 'selling_price' => $data['selling_price'], 'manufactured_at' => $data['manufactured_at'], 'expires_at' => $data['expires_at'] ?? null, 'status' => 'draft', 'notes' => $data['notes'] ?? null, 'created_by' => $userId]);
            $order->update(['number' => 'MO-'.str_pad((string) $order->id, 7, '0', STR_PAD_LEFT)]);
            foreach ($components as $line) { $cost=(string)($line['variant']?->purchase_price ?? $line['product']->purchase_price); $order->components()->create(['product_id' => $line['product']->id, 'product_variant_id'=>$line['variant']?->id, 'quantity' => $line['quantity'], 'unit_cost' => $cost, 'total_cost' => bcmul($cost, $line['quantity'], 4)]); }
            foreach ($expenses as $expense) $order->expenses()->create(['name' => $expense['name'], 'amount' => $expense['amount']]);
            return $order;
        }, 3);
    }

    public function confirm(ManufacturingOrder $order, int $userId): ManufacturingOrder { return $this->transition($order, 'draft', ['status' => 'confirmed', 'confirmed_by' => $userId, 'confirmed_at' => now()]); }
    public function start(ManufacturingOrder $order, int $userId): ManufacturingOrder { return $this->transition($order, 'confirmed', ['status' => 'in_progress', 'started_by' => $userId, 'started_at' => now()]); }
    public function cancel(ManufacturingOrder $order, string $reason, int $userId): ManufacturingOrder
    {
        return DB::transaction(function () use ($order, $reason, $userId) {
            $locked = ManufacturingOrder::lockForUpdate()->findOrFail($order->id);
            if (! in_array($locked->status, ['draft','confirmed','in_progress'], true)) throw ValidationException::withMessages(['status' => 'Only an open manufacturing order can be cancelled.']);
            if ($locked->inventory_transaction_id || $locked->output_lot_id) throw ValidationException::withMessages(['status' => 'This order already has posted stock and cannot be cancelled.']);
            $locked->update(['status'=>'cancelled','cancellation_reason'=>$reason,'cancelled_by'=>$userId,'cancelled_at'=>now()]);
            return $locked->fresh();
        }, 3);
    }
    private function transition(ManufacturingOrder $order, string $from, array $changes): ManufacturingOrder
    {
        return DB::transaction(function () use ($order, $from, $changes) {
            $locked = ManufacturingOrder::lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== $from) throw ValidationException::withMessages(['status' => 'This manufacturing order is no longer in the expected status. Refresh and try again.']);
            $locked->update($changes); return $locked->fresh();
        }, 3);
    }

    public function complete(ManufacturingOrder $order, array $lotSelections, int $userId, array $serialConfig = []): ManufacturingOrder
    {
        return DB::transaction(function () use ($order, $lotSelections, $userId, $serialConfig) {
            $order = ManufacturingOrder::with(['product.unit', 'components.product.unit','components.productVariant'])->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'in_progress') throw ValidationException::withMessages(['status' => 'Only an in-progress manufacturing order can be completed.']);
            $this->validateLocation($order->location_id); $prepared = []; $materialCost = '0';
            foreach ($order->components as $line) {
                $product = Product::with('unit')->lockForUpdate()->findOrFail($line->product_id); $quantity = (string) $line->quantity; $lot = null;
                $this->validateComponent($product, $line->productVariant);
                if ($product->track_lots) {
                    $lot = ProductLot::whereHas('stockItem', function ($q) use ($product, $line) { $q->where('product_id',$product->id); if($line->product_variant_id)$q->whereHas('variant',fn($v)=>$v->where('product_variants.id',$line->product_variant_id)); })->lockForUpdate()->find($lotSelections[$line->id] ?? null);
                    if (! $lot) throw ValidationException::withMessages(['component_lots' => 'Select a valid source lot for '.$product->name.'.']);
                    $available = InventoryMovement::where('product_lot_id', $lot->id)->where('location_id', $order->location_id)->sum('quantity_delta');
                    if (bccomp((string) $available, $quantity, 4) < 0) throw ValidationException::withMessages(['component_lots' => 'Insufficient '.$product->name.' stock in the selected lot.']);
                    $unitCost = (string) $lot->unit_cost;
                } elseif ($line->product_variant_id) {
                    $row=DB::table('product_variant_location_stocks')->where('product_variant_id',$line->product_variant_id)->where('location_id',$order->location_id)->lockForUpdate()->first();
                    if(! $row || bccomp((string)$row->opening_quantity,$quantity,4)<0) throw ValidationException::withMessages(['component_lots'=>'Insufficient '.$product->name.' ('.$line->productVariant->value.') stock at the selected warehouse.']);
                    $unitCost=(string)$line->productVariant->purchase_price;
                } else {
                    $row = DB::table('location_product')->where('product_id', $product->id)->where('location_id', $order->location_id)->lockForUpdate()->first();
                    if (! $row || bccomp((string) $row->opening_quantity, $quantity, 4) < 0) throw ValidationException::withMessages(['component_lots' => 'Insufficient '.$product->name.' stock at the selected warehouse.']);
                    $unitCost = (string) $product->purchase_price;
                }
                $total = bcmul($unitCost, $quantity, 4); $materialCost = bcadd($materialCost, $total, 4); $prepared[] = [$line, $product, $lot, $quantity, $unitCost, $total];
            }
            $totalCost = bcadd($materialCost, (string) $order->expense_cost, 4); $unitCost = bcdiv($totalCost, (string) $order->quantity, 4);
            $transaction = InventoryTransaction::create(['transaction_type' => 'manufacturing', 'reference' => $order->number, 'notes' => $order->notes, 'created_by' => $userId, 'occurred_at' => now()]);
            foreach ($prepared as [$line, $product, $lot, $quantity, $componentUnitCost, $componentTotal]) {
                if ($lot) InventoryMovement::create(['inventory_transaction_id' => $transaction->id, 'product_lot_id' => $lot->id, 'location_id' => $order->location_id, 'quantity_delta' => -$quantity, 'created_at' => now()]);
                elseif($line->product_variant_id) DB::table('product_variant_location_stocks')->where('product_variant_id',$line->product_variant_id)->where('location_id',$order->location_id)->decrement('opening_quantity',$quantity);
                else DB::table('location_product')->where('product_id', $product->id)->where('location_id', $order->location_id)->decrement('opening_quantity', $quantity);
                $line->update(['product_lot_id' => $lot?->id, 'unit_cost' => $componentUnitCost, 'total_cost' => $componentTotal]);
            }
            $stockItem = ProductStockItem::firstOrCreate(['product_id' => $order->product_id]);
            $lot = ProductLot::create(['product_stock_item_id' => $stockItem->id, 'lot_number' => 'PENDING-'.\Illuminate\Support\Str::ulid(), 'manufactured_at' => $order->manufactured_at, 'expires_at' => $order->expires_at, 'unit_cost' => $unitCost, 'selling_price' => $order->selling_price, 'created_by' => $userId]);
            $lot->update(['lot_number' => sprintf('LOT-%s-%06d', $lot->created_at->format('Ymd'), $lot->id)]);
            $order->product->locations()->syncWithoutDetaching([$order->location_id => ['opening_quantity' => 0]]);
            InventoryMovement::create(['inventory_transaction_id' => $transaction->id, 'product_lot_id' => $lot->id, 'location_id' => $order->location_id, 'quantity_delta' => $order->quantity, 'created_at' => now()]);
            if ($order->product->enable_serial) foreach ($this->prepareOutputSerials($order, $serialConfig) as $serial) ProductSerialNumber::create(['product_id'=>$order->product_id,'location_id'=>$order->location_id,'manufacturing_order_id'=>$order->id,'product_lot_id'=>$lot->id,'serial_number'=>$serial]);
            $order->update(['material_cost' => $materialCost, 'total_cost' => $totalCost, 'unit_cost' => $unitCost, 'output_lot_id' => $lot->id, 'inventory_transaction_id' => $transaction->id, 'status' => 'completed', 'completed_by' => $userId, 'completed_at' => now()]);
            return $order->fresh();
        }, 3);
    }

    /** Keeps existing integrations atomic while new UI uses the lifecycle methods. */
    public function process(array $data, int $userId): ManufacturingOrder
    {
        if (isset($data['bill_of_material_id'])) { $order = $this->createDraft($data, $userId); $this->confirm($order, $userId); $this->start($order->fresh(), $userId); return $this->complete($order->fresh(), $data['component_lots'] ?? [], $userId); }
        return DB::transaction(function () use ($data, $userId) {
            $output = Product::with('unit')->findOrFail($data['product_id']); $this->validateOutput($output, (string) $data['quantity']); $this->validateLocation((int) $data['location_id']);
            $order = ManufacturingOrder::create(['number'=>'TMP-'.bin2hex(random_bytes(8)),'product_id'=>$output->id,'location_id'=>$data['location_id'],'quantity'=>$data['quantity'],'selling_price'=>$data['selling_price'],'manufactured_at'=>$data['manufactured_at'],'expires_at'=>$data['expires_at']??null,'status'=>'in_progress','notes'=>$data['notes']??null,'created_by'=>$userId,'confirmed_by'=>$userId,'started_by'=>$userId,'confirmed_at'=>now(),'started_at'=>now()]);
            $order->update(['number'=>'MO-'.str_pad((string)$order->id,7,'0',STR_PAD_LEFT)]);
            foreach ($data['components'] as $component) { $product=Product::findOrFail($component['product_id']); $order->components()->create(['product_id'=>$product->id,'product_lot_id'=>$component['lot_id']??null,'quantity'=>$component['quantity'],'unit_cost'=>$product->purchase_price,'total_cost'=>bcmul((string)$product->purchase_price,(string)$component['quantity'],4)]); }
            foreach (collect($data['expenses']??[])->filter(fn($expense)=>filled($expense['name']??null)) as $expense) $order->expenses()->create(['name'=>$expense['name'],'amount'=>$expense['amount']]);
            $order->update(['expense_cost'=>$order->expenses()->sum('amount')]);
            return $this->complete($order->fresh(), $order->components()->whereNotNull('product_lot_id')->pluck('product_lot_id','id')->all(), $userId);
        }, 3);
    }

    private function validateLocation(int $id): void { if (DB::table('delivery_vehicle_stores')->where('location_id', $id)->exists() || ! DB::table('locations')->where('id', $id)->where('is_active', true)->exists()) throw ValidationException::withMessages(['location_id' => 'Choose an active warehouse or branch location.']); }
    private function validateComponent(Product $product, $variant=null):void { if(! $product->is_active || ! $product->is_raw_material || ! $product->manage_stock || $product->enable_serial || ! in_array($product->product_type,['single','variable'],true)) throw ValidationException::withMessages(['components'=>'Manufacturing materials must be active, non-serial, stock-managed Single or Variable products marked as raw materials.']); if($product->product_type==='variable' && (! $variant || (int)$variant->product_id!==$product->id)) throw ValidationException::withMessages(['components'=>'Choose an exact variation for '.$product->name.'.']); if($product->product_type==='single' && $variant) throw ValidationException::withMessages(['components'=>$product->name.' does not use a variation.']); }
    private function validateOutput(Product $output, string $quantity): void { if (! $output->is_active || ! $output->is_manufacturable || ! $output->manage_stock || ! $output->track_lots || $output->product_type !== 'single') throw ValidationException::withMessages(['product_id' => 'Choose an active, single, lot-tracked manufacturable product.']); if ((! $output->unit?->allow_decimal || $output->enable_serial) && (float) $quantity !== floor((float) $quantity)) throw ValidationException::withMessages(['quantity' => 'The finished product requires a whole-number quantity.']); }

    private function prepareOutputSerials(ManufacturingOrder $order, array $config): array
    {
        $quantity=(int)$order->quantity;
        if ($quantity < 1 || $quantity > 10000) throw ValidationException::withMessages(['serial_mode'=>'Serial-tracked production must contain between 1 and 10,000 whole units.']);
        $mode=$config['serial_mode']??null;
        if (! in_array($mode,['auto','manual'],true)) throw ValidationException::withMessages(['serial_mode'=>'Choose automatic generation or manual serial entry.']);
        if ($mode==='manual') {
            $serials=array_values(array_filter(array_map('trim',preg_split('/[\r\n,]+/',(string)($config['manual_serials']??'')))));
            if (count($serials)!==$quantity) throw ValidationException::withMessages(['manual_serials'=>'Enter exactly '.$quantity.' unique serial number(s), one per manufactured unit.']);
        } else {
            $prefix=trim((string)($config['serial_prefix']??''));
            if ($prefix==='') $prefix=strtoupper(preg_replace('/[^A-Za-z0-9._-]+/','-',trim($order->product->code,'-'))).'-'.$order->manufactured_at->format('Ymd');
            if (! preg_match('/^[A-Za-z0-9._-]{1,60}$/D',$prefix)) throw ValidationException::withMessages(['serial_prefix'=>'Use up to 60 letters, numbers, dots, dashes or underscores for the serial prefix.']);
            $serials=[];$number=1;
            while(count($serials)<$quantity){$candidate=$prefix.'-'.str_pad((string)$number++,6,'0',STR_PAD_LEFT);if(!ProductSerialNumber::where('serial_key',strtolower($candidate))->exists())$serials[]=$candidate;}
        }
        $seen=[];
        foreach($serials as $serial){
            if(strlen($serial)>100||!preg_match('/^[!-~]+$/D',$serial)) throw ValidationException::withMessages(['manual_serials'=>'Serial numbers must use 1–100 printable characters without spaces.']);
            $key=strtolower($serial);
            if(isset($seen[$key])||ProductSerialNumber::where('serial_key',$key)->exists()) throw ValidationException::withMessages(['manual_serials'=>'Duplicate serial number: '.$serial.'. No stock was posted.']);
            $seen[$key]=true;
        }
        return $serials;
    }
}
