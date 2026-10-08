<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreBillOfMaterialRequest;
use App\Models\{BillOfMaterial, Product};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillOfMaterialController extends Controller
{
    public function index()
    {
        $boms = BillOfMaterial::with(['product.unit', 'items'])->latest()->paginate(20);
        return view('manufacturing.boms.index', compact('boms'));
    }

    public function create() { return $this->form(new BillOfMaterial()); }
    public function edit(BillOfMaterial $bom) { $bom->load('items'); return $this->form($bom); }

    private function form(BillOfMaterial $bom)
    {
        $outputs = Product::where('is_active', true)->where('manage_stock',true)->where('is_raw_material',false)->where('product_type','single')->orderBy('name')->get();
        $materials = Product::with(['unit','variants.variationValues'])->where('is_raw_material', true)->where('enable_serial', false)->where('is_active', true)->whereIn('product_type',['single','variable'])->orderBy('name')->get();
        return view('manufacturing.boms.form', compact('bom', 'outputs', 'materials'));
    }

    public function store(StoreBillOfMaterialRequest $request) { return $this->save($request, new BillOfMaterial()); }
    public function update(StoreBillOfMaterialRequest $request, BillOfMaterial $bom) { return $this->save($request, $bom); }

    private function save(StoreBillOfMaterialRequest $request, BillOfMaterial $bom)
    {
        $data = $request->validated();
        $output = Product::findOrFail($data['product_id']);
        if (! $output->track_lots && ($output->locations()->wherePivot('opening_quantity','>',0)->exists() || $output->variants()->whereHas('locationStocks',fn($query)=>$query->where('opening_quantity','>',0))->exists())) {
            throw ValidationException::withMessages(['product_id'=>'Clear this product’s opening stock before using it as a manufactured output. Production will manage its stock by automatically generated lots.']);
        }
        if (collect($data['items'])->contains(fn ($item) => (int) $item['product_id'] === (int) $data['product_id'])) {
            throw ValidationException::withMessages(['items' => 'A finished product cannot consume itself.']);
        }
        $seen=[];
        foreach($data['items'] as $index=>$item){
            $product=Product::with('variants')->findOrFail($item['product_id']);$variantId=$item['product_variant_id']??null;
            if($product->enable_serial) throw ValidationException::withMessages(["items.$index.product_id"=>'Serial-tracked products cannot be consumed as recipe materials. Use a non-serial raw material.']);
            if($product->product_type==='variable' && (!$variantId || !$product->variants->contains('id',(int)$variantId))) throw ValidationException::withMessages(["items.$index.product_variant_id"=>'Select a valid variation for '.$product->name.'.']);
            if($product->product_type==='single' && $variantId) throw ValidationException::withMessages(["items.$index.product_variant_id"=>$product->name.' does not use variations.']);
            $key=$product->id.':'.($variantId?:0);if(isset($seen[$key])) throw ValidationException::withMessages(['items'=>'The same raw material variation cannot be added twice.']);$seen[$key]=true;
        }
        DB::transaction(function () use ($request, $data, $bom, $output) {
            $output->update(['is_manufacturable'=>true,'track_lots'=>true]);
            $bom->fill(collect($data)->except('items')->all());
            $bom->is_active = $request->boolean('is_active');
            if (! $bom->exists) $bom->created_by = $request->user()->id;
            $bom->save();
            $bom->items()->delete();
            foreach ($data['items'] as $position => $item) $bom->items()->create($item + ['position' => $position]);
        });
        return redirect()->route('manufacturing.boms.index')->with('success', 'Bill of Materials saved.');
    }
}
