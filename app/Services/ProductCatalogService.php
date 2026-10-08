<?php

namespace App\Services;

use App\Http\Requests\SaveProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\BusinessSetting;
use App\Models\Unit;
use App\Models\VariationTemplate;
use App\Models\VariationTemplateValue;
use App\Models\TaxRate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns product persistence, prices, locations, variations and attachments.
 * Keeping these coordinated writes here prevents controllers from saving a
 * partially configured product.
 */
class ProductCatalogService
{
    public function delete(Product $product): void
    {
        $files = array_filter([$product->image_path, $product->variant_image_path, $product->brochure_path]);
        DB::transaction(function () use ($product) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            if (DB::table('delivery_transfer_lines')->whereIn('stock_item_id', $product->stockItems()->select('id'))->exists()) {
                throw ValidationException::withMessages(['product' => 'This product has delivery transfer history and cannot be deleted.']);
            }
            if ($product->serialNumbers()->exists()) {
                throw ValidationException::withMessages(['product' => 'This product has serial-number history and cannot be deleted.']);
            }
            if ($product->lots()->exists()) {
                throw ValidationException::withMessages(['product' => 'This product has lot history and cannot be deleted.']);
            }
            // A featured-product assignment is presentation data owned by a
            // business location. It must not keep an otherwise deletable
            // product record alive.
            DB::table('location_featured_products')->where('product_id', $product->id)->delete();
            $stockItemIds = $product->stockItems()->pluck('id');
            DB::table('product_stock_item_variants')->whereIn('product_stock_item_id', $stockItemIds)->delete();
            $product->stockItems()->delete();
            $product->delete();
        });
        $this->cleanupAttachments($files);
    }

    public function saveOpeningStock(Product $product, array $quantities): void
    {
        // Lock the product while replacing opening balances so concurrent stock
        // edits cannot leave one location with an older submitted value.
        DB::transaction(function () use ($product, $quantities) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            if (! $product->manage_stock || $product->enable_serial) {
                throw ValidationException::withMessages(['quantities' => 'Serial products use individual serial numbers for opening stock.']);
            }
            $locationIds = $product->locations()->pluck('locations.id')->map(fn ($id) => (string) $id)->all();
            if (array_diff(array_keys($quantities), $locationIds) || count($locationIds) !== count($quantities)) {
                throw ValidationException::withMessages(['quantities' => 'Enter stock for the assigned locations only.']);
            }
            foreach ($quantities as $locationId => $quantity) {
                if (DB::table('delivery_vehicle_stores')->where('location_id', $locationId)->exists()
                    && bccomp((string) $quantity, (string) $product->locations()->where('locations.id', $locationId)->first()->pivot->opening_quantity, 4) !== 0) {
                    throw ValidationException::withMessages(['quantities' => 'Use Delivery loading/unloading to change vehicle stock.']);
                }
                if (! $product->unit?->allow_decimal && (float) $quantity !== floor((float) $quantity)) {
                    throw ValidationException::withMessages(['quantities' => 'This unit requires whole-number stock.']);
                }
                $product->locations()->updateExistingPivot($locationId, ['opening_quantity' => $quantity]);
            }
        });
    }

    public function saveVariantOpeningStock(Product $product, array $quantities): void
    {
        // Variable stock is stored per variation and location; serial products
        // deliberately use serial records instead of aggregate quantities.
        DB::transaction(function () use ($product, $quantities) {
            $product = Product::with(['variants.locationStocks', 'locations', 'unit'])->lockForUpdate()->findOrFail($product->id);
            if (! $product->manage_stock || $product->enable_serial || $product->product_type !== 'variable') {
                throw ValidationException::withMessages(['quantities' => 'Variation opening stock is available only for non-serial variable products.']);
            }
            $variantIds = $product->variants->pluck('id')->map(fn ($id) => (string) $id)->all();
            $locationIds = $product->locations->pluck('id')->map(fn ($id) => (string) $id)->all();
            if (array_diff(array_keys($quantities), $variantIds) || count($quantities) !== count($variantIds)) {
                throw ValidationException::withMessages(['quantities' => 'Enter stock for every variation.']);
            }
            foreach ($quantities as $variantId => $locationQuantities) {
                if (! is_array($locationQuantities) || array_diff(array_keys($locationQuantities), $locationIds) || count($locationQuantities) !== count($locationIds)) {
                    throw ValidationException::withMessages(['quantities' => 'Enter stock for every assigned location.']);
                }
                foreach ($locationQuantities as $locationId => $quantity) {
                    if (DB::table('delivery_vehicle_stores')->where('location_id', $locationId)->exists()) {
                        $current = $product->variants->firstWhere('id', $variantId)->locationStocks()->where('location_id', $locationId)->value('opening_quantity') ?? 0;
                        if (bccomp((string) $quantity, (string) $current, 4) !== 0) {
                            throw ValidationException::withMessages(['quantities' => 'Use Delivery loading/unloading to change vehicle stock.']);
                        }
                    }
                    if (! $product->unit?->allow_decimal && (float) $quantity !== floor((float) $quantity)) {
                        throw ValidationException::withMessages(['quantities' => 'This unit requires whole-number stock.']);
                    }
                    $product->variants->firstWhere('id', $variantId)->locationStocks()->updateOrCreate(
                        ['location_id' => $locationId], ['opening_quantity' => $quantity]
                    );
                }
            }
        });
    }

    /** @return array{product: Product, action: string} */
    public function save(SaveProductRequest $request, Product $product): array
    {
        $data = $request->validated();
        if (! empty($data['tax_rate_id'])) {
            // Resolve the percentage on the server; a browser cannot override
            // the configured amount for the selected single or group tax.
            $configuredTax = TaxRate::availableForProducts()->findOrFail($data['tax_rate_id']);
            $data['tax_rate'] = $configuredTax->amount;
        } else {
            $data['tax_rate_id'] = null;
        }
        $unit = Unit::findOrFail($data['unit_id']);
        $this->validateStockConfiguration($data, $unit);

        $code = $this->resolveSku($data, $product);
        // BCMath prevents float rounding in tax and margin calculations that
        // later become financial DECIMAL values in the database.
        $factor = bcadd('1', bcdiv((string) $data['tax_rate'], '100', 8), 8);
        $variants = $data['variants'] ?? [];
        $variationTemplateId = $data['variation_template_id'] ?? null;
        $comboItems = $data['combo_items'] ?? [];
        $locationDetails = $data['location_details'] ?? [];
        $this->applyProductTypePricing($data, $product, $variants, $variationTemplateId, $comboItems);

        $data['purchase_price_inc'] = bcmul((string) $data['purchase_price'], $factor, 4);
        if ($data['selling_price_tax_type'] === 'inclusive') {
            $data['selling_price'] = bcdiv((string) $data['selling_price'], $factor, 4);
        }
        $data['margin'] = bccomp((string) $data['purchase_price'], '0', 4) > 0
            ? bcmul(bcdiv(bcsub((string) $data['selling_price'], (string) $data['purchase_price'], 4), (string) $data['purchase_price'], 8), '100', 4)
            : '0';
        if (bccomp($data['margin'], '999999', 4) > 0) {
            throw ValidationException::withMessages(['selling_price' => 'The selling price produces a margin above 999999%.']);
        }

        unset($data['purchase_price_inc'], $data['margin']);
        $action = $data['save_action'];
        $locations = $data['location_ids'];
        unset($data['sku'], $data['location_ids'], $data['location_details'], $data['save_action'], $data['image'], $data['variant_image'], $data['variant_images'], $data['brochure'], $data['variants'], $data['variation_template_id'], $data['combo_items']);
        $data['code'] = $code;
        $data['description'] = ProductDescription::clean($data['description'] ?? '');
        if (! $data['manage_stock'] || $data['product_type'] === 'combo') {
            $data['alert_quantity'] = null;
        }
        if ($data['product_type'] === 'combo') {
            $data['manage_stock'] = false;
        }

        $newFiles = [];
        $oldFiles = [];
        try {
            // Product, locations, variants and combo contents are one business
            // unit. Roll everything back if any related record cannot be saved.
            DB::transaction(function () use ($request, $product, &$data, $locations, $locationDetails, $variants, $variationTemplateId, $comboItems, $factor, &$newFiles, &$oldFiles) {
                $this->lockAndValidateExistingProduct($product, $data, $locations);
                $this->storeAttachments($request, $product, $data, $newFiles, $oldFiles);

                $product->fill($data)->save();
                $product->locations()->sync($locations);
                DB::table('product_location_details')->where('product_id', $product->id)->whereNotIn('location_id', $locations)->delete();
                foreach ($locations as $locationId) {
                    $details = $locationDetails[$locationId] ?? [];
                    DB::table('product_location_details')->updateOrInsert(
                        ['product_id' => $product->id, 'location_id' => $locationId],
                        ['rack' => $details['rack'] ?? null, 'row' => $details['row'] ?? null, 'position' => $details['position'] ?? null]
                    );
                }
                $this->syncVariants($request, $product, $data, $variants, $variationTemplateId, $factor, $locations, $newFiles, $oldFiles);
                $product->comboItems()->sync($product->product_type === 'combo'
                    ? collect($comboItems)->mapWithKeys(fn ($item) => [$item['product_id'] => ['quantity' => $item['quantity']]])->all()
                    : []);
            });
        } catch (\Throwable $exception) {
            $this->cleanupAttachments($newFiles);
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['sku' => 'This SKU is already in use.']);
            }
            throw $exception;
        }

        $this->cleanupAttachments($oldFiles);

        return compact('product', 'action');
    }

    private function validateStockConfiguration(array $data, Unit $unit): void
    {
        if ($data['enable_serial'] && (! $data['manage_stock'] || $unit->allow_decimal)) {
            throw ValidationException::withMessages(['enable_serial' => 'Serial tracking needs Manage Stock and a unit that does not allow decimals.']);
        }
        if ($data['product_type'] === 'combo' && $data['enable_serial']) {
            throw ValidationException::withMessages(['enable_serial' => 'Serial tracking belongs to the individual products inside a combo.']);
        }
        if ($data['track_lots'] && (! $data['manage_stock'] || ($data['enable_serial'] && ! $data['is_manufacturable']) || $data['product_type'] === 'combo')) {
            throw ValidationException::withMessages(['track_lots' => 'Lot tracking requires managed stock. Serial and lot tracking can be combined only for a manufactured product.']);
        }
        if (! $unit->allow_decimal && isset($data['alert_quantity']) && (float) $data['alert_quantity'] !== floor((float) $data['alert_quantity'])) {
            throw ValidationException::withMessages(['alert_quantity' => 'This unit requires a whole-number alert quantity.']);
        }
        if (($data['subcategory_id'] ?? null) && ! ($data['category_id'] ?? null)) {
            throw ValidationException::withMessages(['subcategory_id' => 'Select a parent category first.']);
        }
    }

    private function resolveSku(array $data, Product $product): string
    {
        $enteredSku = trim($data['sku'] ?? '');
        $prefix = strtoupper(trim((string) (BusinessSetting::current()->productSettings?->sku_prefix ?? '')));
        $code = $enteredSku !== '' ? $enteredSku : ($product->code ?? ($prefix !== '' ? $prefix : 'PRD').'-'.Str::ulid());
        if (Product::where('sku_key', strtolower($code))->when($product->exists, fn ($query) => $query->where('id', '!=', $product->id))->exists()) {
            throw ValidationException::withMessages(['sku' => 'This SKU is already in use.']);
        }
        if ($data['barcode_type'] === 'CODE39' && ! preg_match('/^[A-Z0-9.\-]+$/D', $code)) {
            throw ValidationException::withMessages(['sku' => 'CODE39 requires uppercase letters, numbers, dots or hyphens. Choose CODE128 for other SKU formats.']);
        }
        if ($data['barcode_type'] === 'EAN13' && ! $this->validGtin($code, 13)) {
            throw ValidationException::withMessages(['sku' => 'EAN-13 requires exactly 13 digits with a valid check digit.']);
        }
        if ($data['barcode_type'] === 'UPCA' && ! $this->validGtin($code, 12)) {
            throw ValidationException::withMessages(['sku' => 'UPC-A requires exactly 12 digits with a valid check digit.']);
        }

        return $code;
    }

    private function validGtin(string $code, int $length): bool
    {
        if (! preg_match('/^\d{'.$length.'}$/D', $code)) return false;
        $digits = array_map('intval', str_split($code));
        $checkDigit = array_pop($digits);
        $sum = 0;
        foreach ($digits as $index => $digit) {
            $sum += $digit * (($index % 2 === 0) ? ($length === 13 ? 1 : 3) : ($length === 13 ? 3 : 1));
        }
        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }

    private function applyProductTypePricing(array &$data, Product $product, array $variants, ?int $templateId, array $comboItems): void
    {
        if ($data['product_type'] === 'variable') {
            $templateValues = VariationTemplate::findOrFail($templateId)->values;
            foreach ($variants as $variant) {
                if (! in_array($variant['value'], $templateValues, true)) {
                    throw ValidationException::withMessages(['variants' => 'A variation value does not belong to the selected template.']);
                }
            }
            $data['purchase_price'] = min(array_column($variants, 'purchase_price'));
            $data['selling_price'] = min(array_column($variants, 'selling_price'));
        }
        if ($data['product_type'] === 'combo') {
            $itemIds = collect($comboItems)->pluck('product_id');
            if ($product->exists && $itemIds->contains($product->id)) {
                throw ValidationException::withMessages(['combo_items' => 'A combo cannot contain itself.']);
            }
            $pricedItems = Product::whereIn('id', $itemIds)->get()->keyBy('id');
            $data['purchase_price'] = collect($comboItems)->sum(fn ($item) => (float) $pricedItems[$item['product_id']]->purchase_price * (float) $item['quantity']);
            $data['selling_price'] = bcmul(
                (string) $data['purchase_price'],
                bcadd('1', bcdiv((string) $data['margin'], '100', 8), 8),
                4
            );
        }
    }

    private function lockAndValidateExistingProduct(Product $product, array $data, array $locations): void
    {
        if (! $product->exists) {
            return;
        }
        Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
        $product->refresh();
        if (DB::table('delivery_transfer_lines')->whereIn('stock_item_id', $product->stockItems()->select('id'))->exists()) {
            foreach (['product_type', 'unit_id', 'manage_stock', 'enable_serial', 'track_lots'] as $field) {
                if ($data[$field] != $product->$field) {
                    throw ValidationException::withMessages([$field => 'The stock method and unit cannot change after delivery transfers.']);
                }
            }
            $vehicleLocations = DB::table('delivery_vehicle_stores')->pluck('location_id')->all();
            if ($product->locations()->whereIn('locations.id', $vehicleLocations)->whereNotIn('locations.id', $locations)->exists()) {
                throw ValidationException::withMessages(['location_ids' => 'Keep vehicle store locations with delivery history assigned.']);
            }
        }
        if ($product->serialNumbers()->exists() && (! $data['enable_serial'] || ! $data['manage_stock'])) {
            throw ValidationException::withMessages(['enable_serial' => 'This product already has serials. Keep serial tracking and stock management enabled.']);
        }
        if ($product->serialNumbers()->whereNotIn('location_id', $locations)->exists()) {
            throw ValidationException::withMessages(['location_ids' => 'A removed location still has serial numbers for this product.']);
        }
        if ($product->lots()->exists() && $data['product_type'] !== $product->product_type) {
            throw ValidationException::withMessages(['product_type' => 'A product with lot history cannot change its product type.']);
        }
        if ($product->track_lots !== (bool) $data['track_lots']) {
            if ($product->lots()->exists()) {
                throw ValidationException::withMessages(['track_lots' => 'Lot tracking cannot be changed after lots have been received.']);
            }
            if ($data['track_lots'] && ($product->locations()->wherePivot('opening_quantity', '>', 0)->exists() || $product->variants()->whereHas('locationStocks', fn ($query) => $query->where('opening_quantity', '>', 0))->exists())) {
                throw ValidationException::withMessages(['track_lots' => 'Clear current opening stock before enabling lot tracking.']);
            }
        }
        $hasStock = $product->locations()->wherePivot('opening_quantity', '>', 0)->exists();
        if ($hasStock && (! $data['manage_stock'] || $data['enable_serial'] != $product->enable_serial || $data['unit_id'] != $product->unit_id)) {
            throw ValidationException::withMessages(['manage_stock' => 'Opening stock exists. Clear it before changing the stock method or unit.']);
        }
        if ($product->locations()->whereNotIn('locations.id', $locations)->wherePivot('opening_quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages(['location_ids' => 'A removed location still has opening stock.']);
        }
        if ($product->lots()->whereHas('movements', fn ($query) => $query->whereNotIn('location_id', $locations))->exists()) {
            throw ValidationException::withMessages(['location_ids' => 'A location with lot stock history cannot be removed from this product.']);
        }
    }

    private function storeAttachments(SaveProductRequest $request, Product $product, array &$data, array &$newFiles, array &$oldFiles): void
    {
        foreach (['image' => 'image_path', 'variant_image' => 'variant_image_path', 'brochure' => 'brochure_path'] as $field => $column) {
            if (! $request->hasFile($field)) {
                continue;
            }
            if ($field === 'brochure') {
                $path = $request->file($field)->store('products', 'local');
                if (! $path) {
                    throw new \RuntimeException('Unable to store product attachment.');
                }
                $newFiles[] = $path;
                if ($product->$column && ! str_starts_with($product->$column, 'data:')) {
                    $oldFiles[] = $product->$column;
                }
                $data[$column] = $path;
                $data['brochure_name'] = $request->file($field)->getClientOriginalName();
            } else {
                $file = $request->file($field);
                $mime = $file->getMimeType() ?: 'image/jpeg';
                $base64 = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));
                if ($product->$column && ! str_starts_with($product->$column, 'data:')) {
                    $oldFiles[] = $product->$column;
                }
                $data[$column] = $base64;
            }
        }
    }

    private function syncVariants(SaveProductRequest $request, Product $product, array $data, array $variants, ?int $templateId, string $factor, array $locations, array &$newFiles, array &$oldFiles): void
    {
        $existingVariants = $product->variants()->get()->keyBy('value');
        $keptIds=[];
        if ($product->product_type === 'variable') {
            foreach ($variants as $index => $variant) {
                $purchase = (string) $variant['purchase_price'];
                $selling = $data['selling_price_tax_type'] === 'inclusive' ? bcdiv((string) $variant['selling_price'], $factor, 4) : (string) $variant['selling_price'];
                $margin = bccomp($purchase, '0', 4) > 0 ? bcmul(bcdiv(bcsub($selling, $purchase, 4), $purchase, 8), '100', 4) : '0';
                $imagePath = $existingVariants->get($variant['value'])?->image_path;
                if ($request->hasFile("variant_images.$index")) {
                    $file = $request->file("variant_images.$index");
                    $mime = $file->getMimeType() ?: 'image/jpeg';
                    $newPath = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file->getRealPath()));
                    if ($imagePath && ! str_starts_with($imagePath, 'data:')) {
                        $oldFiles[] = $imagePath;
                    }
                    $imagePath = $newPath;
                }
                $savedVariant=$product->variants()->updateOrCreate([
                    'variation_template_value_id' => VariationTemplateValue::where('variation_template_id', $templateId)->where('value', $variant['value'])->value('id'),
                ],[
                    'sku' => $this->variantSku($variant, $product, $existingVariants->get($variant['value'])?->sku),
                    'purchase_price' => $purchase,
                    'selling_price' => $selling,
                    'image_path' => $imagePath,
                ]);
                $keptIds[]=$savedVariant->id;
                foreach ($locations as $locationId) {
                    $savedVariant->locationStocks()->firstOrCreate(['location_id' => $locationId], ['opening_quantity' => 0]);
                }
                $savedVariant->locationStocks()->whereNotIn('location_id', $locations)->delete();
            }
        }
        if (\App\Models\ProductSerialNumber::whereIn('product_variant_id',$product->variants()->whereNotIn('id',$keptIds)->pluck('id'))->exists()) {
            throw ValidationException::withMessages(['variants'=>'A variant with serial-number history cannot be removed.']);
        }
        $removedVariantIds = $product->variants()->whereNotIn('id', $keptIds)->pluck('id');
        if (\App\Models\ProductLot::whereHas('stockItem.variant', fn ($query) => $query->whereIn('product_variants.id', $removedVariantIds))->exists()) {
            throw ValidationException::withMessages(['variants' => 'A variant with lot history cannot be removed.']);
        }
        $removedStockItems = \App\Models\ProductStockItem::whereHas('variant', fn ($query) => $query->whereIn('product_variants.id', $removedVariantIds))->get();
        foreach ($removedStockItems as $stockItem) {
            if (DB::table('delivery_transfer_lines')->where('stock_item_id', $stockItem->id)->exists()) {
                throw ValidationException::withMessages(['variants' => 'A variation with delivery history cannot be removed.']);
            }
            $stockItem->variant()->detach();
            $stockItem->delete();
        }
        $product->variants()->whereNotIn('id',$keptIds)->delete();
        foreach ($existingVariants as $oldVariant) {
            if ($oldVariant->image_path && ! str_starts_with($oldVariant->image_path, 'data:') && ($product->product_type !== 'variable' || ! collect($variants)->contains('value', $oldVariant->value))) {
                $oldFiles[] = $oldVariant->image_path;
            }
        }
    }

    private function variantSku(array $variant, Product $product, ?string $existingSku): string
    {
        $enteredSku = trim($variant['sku'] ?? '');
        if ($enteredSku !== '') {
            return $enteredSku;
        }
        if ($existingSku) {
            return $existingSku;
        }

        $suffix = strtoupper(Str::slug($variant['value'], '-')) ?: 'VAR';
        $candidate = substr($product->code, 0, 60).'-'.substr($suffix, 0, 30);

        return ProductVariant::where('sku', $candidate)->exists()
            ? 'VAR-'.Str::ulid()
            : $candidate;
    }

    private function cleanupAttachments(array $paths): void
    {
        $diskPaths = array_filter($paths, fn ($path) => $path && ! str_starts_with($path, 'data:'));
        if ($diskPaths === []) {
            return;
        }
        try {
            if (! Storage::disk('local')->delete(array_values($diskPaths))) {
                Log::warning('Product attachment cleanup needs retry.', ['paths' => $diskPaths]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
