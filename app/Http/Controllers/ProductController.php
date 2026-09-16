<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductFilterRequest;
use App\Http\Requests\QuickReferenceRequest;
use App\Http\Requests\SaveOpeningStockRequest;
use App\Http\Requests\SaveProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductSerialNumber;
use App\Models\Unit;
use App\Models\VariationTemplate;
use App\Services\ProductCatalogService;
use App\Services\ProductStockReport;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(ProductFilterRequest $request, ProductStockReport $stockReport)
    {
        $filters = $request->validated();
        $products = Product::query()
            ->with(['unit', 'brand', 'category', 'locations'])
            ->when($filters['product_type'] ?? null, fn ($query, $value) => $query->where('product_type', $value))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('category_id', $value))
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
            ->selectRaw('product_id, location_id, COUNT(*) as quantity')
            ->where('status', 'available')
            ->whereIn('product_id', $products->pluck('id'))
            ->groupBy('product_id', 'location_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id.':'.$row->location_id);
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

    public function create()
    {
        return view('products.form', $this->references() + ['product' => new Product]);
    }

    public function store(SaveProductRequest $request, ProductCatalogService $catalog)
    {
        return $this->savedResponse($request, $catalog->save($request, new Product));
    }

    public function edit(Product $product)
    {
        return view('products.form', $this->references() + [
            'product' => $product->load(['locations', 'variants.template', 'comboItems']),
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

    public function opening(Product $product)
    {
        return view('products.opening', ['product' => $product->load('locations')]);
    }

    public function saveOpening(SaveOpeningStockRequest $request, Product $product, ProductCatalogService $catalog)
    {
        $catalog->saveOpeningStock($product, $request->validated('quantities'));

        return back()->with('success', 'Opening stock saved.');
    }

    public function attachment(Product $product, string $kind)
    {
        abort_unless(in_array($kind, ['image', 'variant_image', 'brochure'], true), 404);
        $path = $product->{$kind.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return $kind === 'brochure'
            ? Storage::disk('local')->download($path, basename($product->brochure_name ?? 'brochure'))
            : response()->file(Storage::disk('local')->path($path), ['X-Content-Type-Options' => 'nosniff']);
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

    private function references(): array
    {
        return [
            'units' => Unit::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(),
            'variationTemplates' => VariationTemplate::orderBy('name')->get(),
            'comboProducts' => Product::where('product_type', '!=', 'combo')->orderBy('name')->get(['id', 'name', 'code', 'selling_price']),
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
            default => route('products.catalog.index'),
        };

        if ($request->expectsJson()) {
            $request->session()->flash('success', 'Product saved with SKU '.$product->code.'.');

            return response()->json(['status' => 'success', 'redirect' => $redirect]);
        }

        $message = match ($action) {
            'another' => 'Product saved. Add another product.',
            'opening' => 'Product saved. Add opening stock below.',
            default => 'Product saved with SKU '.$product->code.'.',
        };

        return redirect()->to($redirect)->with('success', $message);
    }
}
