<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductFilterRequest;
use App\Http\Requests\QuickReferenceRequest;
use App\Http\Requests\SaveOpeningStockRequest;
use App\Http\Requests\SaveProductRequest;
use App\Models\Brand;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductSerialNumber;
use App\Models\Unit;
use App\Models\VariationTemplate;
use App\Models\Warranty;
use App\Models\TaxRate;
use App\Models\SellingPriceGroup;
use App\Services\ProductCatalogService;
use App\Services\ProductStockReport;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(ProductFilterRequest $request, ProductStockReport $stockReport)
    {
        $filters = $request->validated();
        $products = Product::query()
            ->with(['unit', 'brand', 'warranty', 'category', 'locations', 'variants.variationValues', 'variants.locationStocks'])
            ->when($filters['product_type'] ?? null, fn ($query, $value) => $query->where('product_type', $value))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->whereHas('selectedCategory', fn ($category) => $category->where('id', $value)->orWhere('parent_id', $value)))
            ->when($filters['subcategory_id'] ?? null, fn ($query, $value) => $query->where('selected_category_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('is_active', $value === 'active'))
            ->when($filters['unit_id'] ?? null, fn ($query, $value) => $query->where('unit_id', $value))
            ->when($filters['brand_id'] ?? null, fn ($query, $value) => $query->where('brand_id', $value))
            ->when($filters['location_id'] ?? null, fn ($query, $value) => $query->whereHas('locations', fn ($locations) => $locations->whereKey($value)))
            ->when(isset($filters['tax_rate']), fn ($query) => $query->where('tax_rate', $filters['tax_rate']))
            ->when($filters['stock_status'] ?? null, function ($query, $value) {
                match ($value) {
                    'managed' => $query->where('manage_stock', true),
                    'unmanaged' => $query->where('manage_stock', false),
                    'serial' => $query->where('enable_serial', true),
                };
            })
            ->when($request->boolean('not_for_selling'), fn ($query) => $query->where('not_for_selling', true))
            ->latest('id')
            ->get();

        $serialStock = ProductSerialNumber::query()
            ->leftJoin('product_variants as stock_variant', 'stock_variant.id', '=', 'product_serial_numbers.product_variant_id')
            ->selectRaw('COALESCE(product_serial_numbers.product_id, stock_variant.product_id) as product_id, product_serial_numbers.product_variant_id, location_id, COUNT(*) as quantity')
            ->where('status', 'available')
            ->whereIn(\Illuminate\Support\Facades\DB::raw('COALESCE(product_serial_numbers.product_id, stock_variant.product_id)'), $products->pluck('id'))
            ->groupByRaw('COALESCE(product_serial_numbers.product_id, stock_variant.product_id), product_serial_numbers.product_variant_id, location_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id.':'.($row->product_variant_id ?: 'product').':'.$row->location_id);
        $productDetails = $products->mapWithKeys(fn (Product $product) => [
            $product->id => [
                'name' => $product->name,
                'sku' => $product->code,
                'unit' => $product->unit?->name ?? 'Not set',
                'brand' => $product->brand?->name ?? 'Not set',
                'category' => $product->category?->name ?? 'Not set',
                'locations' => $product->locations->pluck('name')->implode(', ') ?: 'Not set',
                'purchase_price' => number_format($product->purchase_price, 2),
                'selling_price' => number_format($product->selling_price, 2),
                'serial_tracking' => $product->enable_serial ? 'Enabled' : 'Disabled',
            ],
        ]);
        $report = $stockReport->build($products, $serialStock);

        return view('products.index', $this->references() + [
            'products' => $products,
            'serialStock' => $serialStock,
            'productDetails' => $productDetails,
            'stockRows' => $report['rows'],
            'stockTotals' => $report['totals'],
        ]);
    }

    public function create(\Illuminate\Http\Request $request)
    {
        $product = new Product;
        if ($request->filled('d')) {
            $source = Product::with(['locations', 'variants.variationValue.template', 'comboItems'])->findOrFail($request->integer('d'));
            $product = $source->replicate(['code', 'sku_key', 'image_path', 'variant_image_path', 'brochure_path', 'brochure_name']);
            $product->name = $source->name.' (Copy)';
            $product->code = null;
            $product->setRelation('locations', $source->locations);
            $product->setRelation('variants', $source->variants);
            $product->setRelation('comboItems', $source->comboItems);
        }
        $locationDetails = isset($source) ? \Illuminate\Support\Facades\DB::table('product_location_details')->where('product_id', $source->id)->get()->keyBy('location_id') : collect();
        return view('products.form', $this->references($request->filled('d') ? $source : null) + compact('product', 'locationDetails'));
    }

    public function store(SaveProductRequest $request, ProductCatalogService $catalog)
    {
        return $this->savedResponse($request, $catalog->save($request, new Product));
    }

    public function edit(Product $product)
    {
        return view('products.form', $this->references($product) + [
            'product' => $product->load(['locations', 'variants.variationValue.template', 'comboItems']),
            'locationDetails' => \Illuminate\Support\Facades\DB::table('product_location_details')->where('product_id', $product->id)->get()->keyBy('location_id'),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, ProductCatalogService $catalog)
    {
        return $this->savedResponse($request, $catalog->save($request, $product));
    }

    public function destroy(Product $product, ProductCatalogService $catalog)
    {
        $catalog->delete($product);

        return redirect()->route('products.catalog.index')->with('success', 'Product deleted successfully.');
    }

    public function duplicate(Product $product)
    {
        return redirect()->route('products.catalog.create', ['d' => $product->id]);
    }

    public function prices(Product $product)
    {
        return view('products.prices', [
            'product' => $product->load('sellingPrices'),
            'groups' => SellingPriceGroup::orderBy('name')->get(),
        ]);
    }

    public function savePrices(\Illuminate\Http\Request $request, Product $product)
    {
        $data = $request->validate([
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'],
        ]);
        $this->databaseTransaction(function () use ($product, $data) {
            foreach (SellingPriceGroup::pluck('id') as $groupId) {
                $price = $data['prices'][$groupId] ?? null;
                if ($price === null || $price === '') $product->sellingPrices()->where('selling_price_group_id', $groupId)->delete();
                else $product->sellingPrices()->updateOrCreate(['selling_price_group_id' => $groupId], ['selling_price' => $price]);
            }
        });
        return redirect()->route('products.catalog.index')->with('success', 'Selling price group prices saved.');
    }

    public function bulk(\Illuminate\Http\Request $request, ProductCatalogService $catalog)
    {
        $data = $request->validate(['action'=>['required',\Illuminate\Validation\Rule::in(['deactivate','activate','delete','add_location','remove_location'])],'product_ids'=>['required','array','min:1'],'product_ids.*'=>['integer','exists:products,id'],'location_id'=>['nullable','integer','exists:locations,id']]);
        if (in_array($data['action'], ['add_location','remove_location'], true) && empty($data['location_id'])) return back()->withErrors(['location_id'=>'Select a business location.']);
        $products = Product::whereIn('id',$data['product_ids'])->get();

        if ($data['action'] === 'delete') {
            $hasRelatedTransactions = false;

            foreach ($products as $product) {
                try {
                    $catalog->delete($product);
                } catch (\Illuminate\Validation\ValidationException) {
                    $hasRelatedTransactions = true;
                }
            }

            if ($hasRelatedTransactions) {
                return back()->with('error', "Some products couldn't be deleted because it has transactions related to it.");
            }

            return back()->with('success', count($products).' products deleted successfully.');
        }

        foreach ($products as $product) {
            match ($data['action']) {
                'activate' => $product->update(['is_active'=>true]),
                'deactivate' => $product->update(['is_active'=>false]),
                'add_location' => $product->locations()->syncWithoutDetaching([$data['location_id']=>['opening_quantity'=>0]]),
                'remove_location' => $product->locations()->detach($data['location_id']),
            };
        }
        return back()->with('success', count($products).' products updated successfully.');
    }

    public function opening(Product $product)
    {
        return view('products.opening', ['product' => $product->load('locations', 'variants.variationValues', 'variants.variationValue', 'variants.locationStocks')]);
    }

    public function saveOpening(SaveOpeningStockRequest $request, Product $product, ProductCatalogService $catalog)
    {
        if ($product->product_type === 'variable') {
            $catalog->saveVariantOpeningStock($product, $request->validated('quantities'));
        } else {
            $catalog->saveOpeningStock($product, $request->validated('quantities'));
        }

        if ($request->boolean('return_to_products')) {
            return redirect()->route('products.catalog.index')->with('success', 'Opening stock saved.');
        }

        return back()->with('success', 'Opening stock saved successfully.');
    }

    public function attachment(Product $product, string $kind)
    {
        abort_unless(in_array($kind, ['image', 'variant_image', 'brochure'], true), 404);
        $value = $product->{$kind.'_path'};
        abort_unless((bool) $value, 404);

        if ($kind === 'brochure') {
            abort_unless(Storage::disk('local')->exists($value), 404);

            return Storage::disk('local')->download($value, basename($product->brochure_name ?? 'brochure'));
        }

        if (str_starts_with($value, 'data:image/')) {
            $parts = explode(',', $value, 2);
            if (count($parts) === 2) {
                preg_match('/data:(image\/[a-zA-Z0-9.-]+);base64/', $parts[0], $matches);
                $mime = $matches[1] ?? 'image/png';
                $decoded = base64_decode($parts[1]);

                return response($decoded, 200, [
                    'Content-Type' => $mime,
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        abort_unless(Storage::disk('local')->exists($value), 404);

        return response()->file(Storage::disk('local')->path($value), ['X-Content-Type-Options' => 'nosniff']);
    }

    public function quickReference(QuickReferenceRequest $request)
    {
        $data = $request->validated();
        $kind = $data['kind'];
        unset($data['kind']);
        $model = ['unit' => Unit::class, 'brand' => Brand::class, 'category' => Category::class, 'location' => Location::class][$kind];
        $record = $this->databaseTransaction(
            fn () => $model::create($data),
            ucfirst($kind).' with these details already exists.',
            'name'
        );

        return response()->json($record, 201);
    }

    private function references(?Product $product = null): array
    {
        $businessSettings = BusinessSetting::current();
        $defaultMargin = $businessSettings->default_profit_percent ?? 25;
        $productSettings = $businessSettings->productSettings?->toArray() ?? [];

        return [
            'units' => Unit::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'warranties' => Warranty::orderBy('name')->get(),
            'taxRates' => TaxRate::availableForProducts()->orderBy('is_tax_group')->orderBy('name')->get(),
            'categories' => Category::tree(),
            'locations' => Location::query()
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('locations', 'is_active'), function ($query) use ($product) {
                    $assignedIds = $product?->locations()->pluck('locations.id')->all() ?? [];
                    $query->where(fn ($locations) => $locations->where('is_active', true)->orWhereIn('id', $assignedIds));
                })
                ->orderBy('name')->get(),
            'variationTemplates' => VariationTemplate::with('valueRecords')->orderBy('name')->get(),
            'comboProducts' => Product::where('product_type', '!=', 'combo')->orderBy('name')->get(['id', 'name', 'code', 'purchase_price', 'selling_price']),
            'defaultMargin' => $defaultMargin,
            'productSettings' => $productSettings,
        ];
    }

    private function savedResponse(SaveProductRequest $request, array $result)
    {
        /** @var Product $product */
        $product = $result['product'];
        $action = $result['action'];
        $redirect = match ($action) {
            'another' => route('products.catalog.create'),
            'opening' => route('products.catalog.opening', $product),
            'prices' => route('products.catalog.prices', $product),
            default => route('products.catalog.index'),
        };

        if ($request->expectsJson()) {
            $request->session()->flash('success', 'Product saved with SKU '.$product->code.'.');

            return response()->json(['status' => 'success', 'redirect' => $redirect]);
        }

        $message = match ($action) {
            'another' => 'Product saved. Add another product.',
            'opening' => 'Product saved. Add opening stock below.',
            'prices' => 'Product saved. Add customer-group prices below.',
            default => 'Product saved with SKU '.$product->code.'.',
        };

        return redirect()->to($redirect)->with('success', $message);
    }
}
