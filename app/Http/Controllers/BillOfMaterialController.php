<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillOfMaterialRequest;
use App\Models\BillOfMaterial;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Class BillOfMaterialController
 *
 * Manages production recipes, bills of materials (BOM), raw material ratios,
 * and component ingredient requirements for manufactured outputs.
 */
class BillOfMaterialController extends Controller
{
    /**
     * Redirect to the manufacturing dashboard order creation view.
     *
     * @return RedirectResponse
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('manufacturing.create');
    }

    /**
     * Redirect to create a new production recipe.
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('manufacturing.create', ['recipe' => 'new']);
    }

    /**
     * Redirect to edit an existing production recipe.
     *
     * @param BillOfMaterial $bom
     * @return RedirectResponse
     */
    public function edit(BillOfMaterial $bom): RedirectResponse
    {
        return redirect()->route('manufacturing.create', ['recipe' => $bom->id]);
    }

    /**
     * Store a newly created bill of materials recipe.
     *
     * @param StoreBillOfMaterialRequest $request
     * @return RedirectResponse
     */
    public function store(StoreBillOfMaterialRequest $request): RedirectResponse
    {
        return $this->save($request, new BillOfMaterial());
    }

    /**
     * Update an existing bill of materials recipe.
     *
     * @param StoreBillOfMaterialRequest $request
     * @param BillOfMaterial $bom
     * @return RedirectResponse
     */
    public function update(StoreBillOfMaterialRequest $request, BillOfMaterial $bom): RedirectResponse
    {
        return $this->save($request, $bom);
    }

    /**
     * Validate business constraints and atomically persist recipe definition and component line items.
     *
     * @param StoreBillOfMaterialRequest $request
     * @param BillOfMaterial $bom
     * @return RedirectResponse
     */
    private function save(StoreBillOfMaterialRequest $request, BillOfMaterial $bom): RedirectResponse
    {
        try {
            $data = $request->validated();
            $output = Product::findOrFail($data['product_id']);

            // When a product is converted into a manufactured item, its inventory must be tracked by lots.
            if (! $output->track_lots && ($output->locations()->wherePivot('opening_quantity', '>', 0)->exists() || $output->variants()->whereHas('locationStocks', fn ($query) => $query->where('opening_quantity', '>', 0))->exists())) {
                throw ValidationException::withMessages([
                    'product_id' => 'Clear this product’s opening stock before using it as a manufactured output. Production will manage its stock by automatically generated lots.',
                ]);
            }

            // Invariant: Finished product cannot be its own raw component ingredient.
            if (collect($data['items'])->contains(fn ($item) => (int) $item['product_id'] === (int) $data['product_id'])) {
                throw ValidationException::withMessages([
                    'items' => 'A finished product cannot consume itself.',
                ]);
            }

            $seen = [];
            foreach ($data['items'] as $index => $item) {
                $product = Product::with('variants')->findOrFail($item['product_id']);
                $variantId = $item['product_variant_id'] ?? null;

                if ($product->enable_serial) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => 'Serial-tracked products cannot be consumed as recipe materials. Use a non-serial raw material.',
                    ]);
                }

                if ($product->product_type === 'variable' && (! $variantId || ! $product->variants->contains('id', (int) $variantId))) {
                    throw ValidationException::withMessages([
                        "items.$index.product_variant_id" => 'Select a valid variation for ' . $product->name . '.',
                    ]);
                }

                if ($product->product_type === 'single' && $variantId) {
                    throw ValidationException::withMessages([
                        "items.$index.product_variant_id" => $product->name . ' does not use variations.',
                    ]);
                }

                $key = $product->id . ':' . ($variantId ?: 0);
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages([
                        'items' => 'The same raw material variation cannot be added twice.',
                    ]);
                }
                $seen[$key] = true;
            }

            DB::transaction(function () use ($request, $data, $bom, $output) {
                $output->update([
                    'is_manufacturable' => true,
                    'track_lots' => true,
                ]);

                $bom->fill(collect($data)->except('items')->all());
                $bom->is_active = $request->boolean('is_active');
                if (! $bom->exists) {
                    $bom->created_by = $request->user()->id;
                }
                $bom->save();

                $bom->items()->delete();
                foreach ($data['items'] as $position => $item) {
                    $bom->items()->create($item + ['position' => $position]);
                }
            });

            return redirect()->route('manufacturing.create')->with('success', 'Manufacturing materials saved. Continue with the manufacturing order.');
        } catch (ValidationException|HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to save bill of materials', [
                'error' => $e->getMessage(),
                'payload' => $request->except(['_token']),
            ]);

            return back()->withInput()->with('error', 'Unable to save manufacturing materials: ' . $e->getMessage());
        }
    }
}
