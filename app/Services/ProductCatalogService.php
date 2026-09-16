<?php

namespace App\Services;

use App\Http\Requests\SaveProductRequest;
use App\Models\Product;
use App\Models\Unit;
use App\Models\VariationTemplate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCatalogService
{
    public function delete(Product $product): void
    {
        $files = array_filter([$product->image_path, $product->variant_image_path, $product->brochure_path]);
        DB::transaction(function () use ($product) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            if ($product->serialNumbers()->exists()) {
                throw ValidationException::withMessages(['product' => 'This product has serial-number history and cannot be deleted.']);
            }
            $product->delete();
        });
        $this->cleanupAttachments($files);
    }

    public function saveOpeningStock(Product $product, array $quantities): void
    {
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
                if (! $product->unit?->allow_decimal && (float) $quantity !== floor((float) $quantity)) {
                    throw ValidationException::withMessages(['quantities' => 'This unit requires whole-number stock.']);
                }
                $product->locations()->updateExistingPivot($locationId, ['opening_quantity' => $quantity]);
            }
        });
    }

    /** @return array{product: Product, action: string} */
    public function save(SaveProductRequest $request, Product $product): array
    {
        $data = $request->validated();
        $unit = Unit::findOrFail($data['unit_id']);
        $this->validateStockConfiguration($data, $unit);

        $code = $this->resolveSku($data, $product);
        $factor = bcadd('1', bcdiv((string) $data['tax_rate'], '100', 8), 8);
        $variants = $data['variants'] ?? [];
        $variationTemplateId = $data['variation_template_id'] ?? null;
        $comboItems = $data['combo_items'] ?? [];
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

        $action = $data['save_action'];
        $locations = $data['location_ids'];
        unset($data['sku'], $data['location_ids'], $data['save_action'], $data['image'], $data['variant_image'], $data['variant_images'], $data['brochure'], $data['variants'], $data['variation_template_id'], $data['combo_items']);
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
            DB::transaction(function () use ($request, $product, &$data, $locations, $variants, $variationTemplateId, $comboItems, $factor, &$newFiles, &$oldFiles) {
                $this->lockAndValidateExistingProduct($product, $data, $locations);
                $this->storeAttachments($request, $product, $data, $newFiles, $oldFiles);

                $product->fill($data)->save();
                $product->locations()->sync($locations);
                $this->syncVariants($request, $product, $data, $variants, $variationTemplateId, $factor, $newFiles, $oldFiles);
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
        $code = $enteredSku !== '' ? $enteredSku : ($product->code ?? 'PRD-'.Str::ulid());
        if (Product::where('sku_key', strtolower($code))->when($product->exists, fn ($query) => $query->where('id', '!=', $product->id))->exists()) {
            throw ValidationException::withMessages(['sku' => 'This SKU is already in use.']);
        }
        if ($data['barcode_type'] === 'CODE39' && ! preg_match('/^[A-Z0-9.\-]+$/D', $code)) {
            throw ValidationException::withMessages(['sku' => 'CODE39 requires uppercase letters, numbers, dots or hyphens. Choose CODE128 for other SKU formats.']);
        }

        return $code;
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
            $data['selling_price'] = collect($comboItems)->sum(fn ($item) => (float) $pricedItems[$item['product_id']]->selling_price * (float) $item['quantity']);
        }
    }

    private function lockAndValidateExistingProduct(Product $product, array $data, array $locations): void
    {
        if (! $product->exists) {
            return;
        }
        Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
        $product->refresh();
        if ($product->serialNumbers()->exists() && (! $data['enable_serial'] || ! $data['manage_stock'])) {
            throw ValidationException::withMessages(['enable_serial' => 'This product already has serials. Keep serial tracking and stock management enabled.']);
        }
        if ($product->serialNumbers()->whereNotIn('location_id', $locations)->exists()) {
            throw ValidationException::withMessages(['location_ids' => 'A removed location still has serial numbers for this product.']);
        }
        $hasStock = $product->locations()->wherePivot('opening_quantity', '>', 0)->exists();
        if ($hasStock && (! $data['manage_stock'] || $data['enable_serial'] != $product->enable_serial || $data['unit_id'] != $product->unit_id)) {
            throw ValidationException::withMessages(['manage_stock' => 'Opening stock exists. Clear it before changing the stock method or unit.']);
        }
        if ($product->locations()->whereNotIn('locations.id', $locations)->wherePivot('opening_quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages(['location_ids' => 'A removed location still has opening stock.']);
        }
    }

    private function storeAttachments(SaveProductRequest $request, Product $product, array &$data, array &$newFiles, array &$oldFiles): void
    {
        foreach (['image' => 'image_path', 'variant_image' => 'variant_image_path', 'brochure' => 'brochure_path'] as $field => $column) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $path = $request->file($field)->store('products', 'local');
            if (! $path) {
                throw new \RuntimeException('Unable to store product attachment.');
            }
            $newFiles[] = $path;
            if ($product->$column) {
                $oldFiles[] = $product->$column;
            }
            $data[$column] = $path;
            if ($field === 'brochure') {
                $data['brochure_name'] = $request->file($field)->getClientOriginalName();
            }
        }
    }

    private function syncVariants(SaveProductRequest $request, Product $product, array $data, array $variants, ?int $templateId, string $factor, array &$newFiles, array &$oldFiles): void
    {
        $existingVariants = $product->variants()->get()->keyBy('value');
        $product->variants()->delete();
        if ($product->product_type === 'variable') {
            foreach ($variants as $index => $variant) {
                $purchase = (string) $variant['purchase_price'];
                $selling = $data['selling_price_tax_type'] === 'inclusive' ? bcdiv((string) $variant['selling_price'], $factor, 4) : (string) $variant['selling_price'];
                $margin = bccomp($purchase, '0', 4) > 0 ? bcmul(bcdiv(bcsub($selling, $purchase, 4), $purchase, 8), '100', 4) : '0';
                $imagePath = $existingVariants->get($variant['value'])?->image_path;
                if ($request->hasFile("variant_images.$index")) {
                    $newPath = $request->file("variant_images.$index")->store('products/variants', 'local');
                    if (! $newPath) {
                        throw new \RuntimeException('Unable to store variation image.');
                    }
                    $newFiles[] = $newPath;
                    if ($imagePath) {
                        $oldFiles[] = $imagePath;
                    }
                    $imagePath = $newPath;
                }
                $product->variants()->create([
                    'variation_template_id' => $templateId,
                    'value' => $variant['value'],
                    'sku' => $variant['sku'],
                    'purchase_price' => $purchase,
                    'purchase_price_inc' => bcmul($purchase, $factor, 4),
                    'margin' => $margin,
                    'selling_price' => $selling,
                    'image_path' => $imagePath,
                ]);
            }
        }
        foreach ($existingVariants as $oldVariant) {
            if ($oldVariant->image_path && ($product->product_type !== 'variable' || ! collect($variants)->contains('value', $oldVariant->value))) {
                $oldFiles[] = $oldVariant->image_path;
            }
        }
    }

    private function cleanupAttachments(array $paths): void
    {
        if ($paths === []) {
            return;
        }
        try {
            if (! Storage::disk('local')->delete($paths)) {
                Log::warning('Product attachment cleanup needs retry.', ['paths' => $paths]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
