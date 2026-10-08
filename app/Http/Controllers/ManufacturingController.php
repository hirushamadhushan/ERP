<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreManufacturingOrderRequest;
use App\Models\{BillOfMaterial, InventoryMovement, Location, ManufacturingOrder, Product};
use App\Services\ManufacturingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManufacturingController extends Controller
{
    public function index(Request $request)
    {
        $orders = ManufacturingOrder::with(['product.unit','location','outputLot','creator'])->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('location_id', $request->user()->locations()->pluck('locations.id')))->latest()->paginate(20);
        $counts = ManufacturingOrder::selectRaw('status, COUNT(*) total')->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('location_id', $request->user()->locations()->pluck('locations.id')))->groupBy('status')->pluck('total','status');
        return view('manufacturing.index', compact('orders','counts'));
    }

    public function create(Request $request)
    {
        $locations = Location::where('is_active', true)->whereNotIn('id', DB::table('delivery_vehicle_stores')->select('location_id'))->when(! $request->user()->all_locations, fn ($q) => $q->whereIn('id', $request->user()->locations()->pluck('locations.id')))->orderBy('name')->get();
        // Show every eligible finished product in the wizard. A product becomes
        // formally manufacturable (and gets lot tracking) when its recipe is saved.
        $outputs = Product::with('unit')->where('manage_stock', true)->where('is_raw_material', false)->where('product_type','single')->where('is_active', true)->orderBy('name')->get();
        $boms = BillOfMaterial::with(['items.product.unit','items.productVariant.variationValues'])->where('is_active', true)->whereIn('product_id', $outputs->pluck('id'))->orderBy('code')->get();
        $bomData = $boms->map(fn ($bom) => ['id'=>$bom->id,'code'=>$bom->code,'product_id'=>$bom->product_id,'output_quantity'=>(float)$bom->output_quantity,'items'=>$bom->items->map(fn($item)=>['name'=>$item->product->name,'variant'=>$item->productVariant?->value,'sku'=>$item->productVariant?->sku ?: $item->product->code,'quantity'=>(float)$item->quantity,'unit'=>$item->product->unit?->short_name,'unit_cost'=>(float)($item->productVariant?->purchase_price ?? $item->product->purchase_price)])]);
        return view('manufacturing.create', compact('locations','outputs','bomData'));
    }

    public function store(StoreManufacturingOrderRequest $request, ManufacturingService $service)
    {
        $this->authorizeLocation($request, (int) $request->validated('location_id'));
        $order = $service->createDraft($request->validated(), $request->user()->id);
        return redirect()->route('manufacturing.show', $order)->with('success', 'Draft manufacturing order created. Review and confirm it before production.');
    }

    public function show(Request $request, ManufacturingOrder $order)
    {
        $this->authorizeLocation($request, $order->location_id);
        $order->load(['product.unit','billOfMaterial','location','outputLot','outputSerials','components.product.unit','components.productVariant.variationValues','components.lot','expenses','creator','confirmer','starter','completer','canceller']);
        $lotOptions = [];
        if ($order->status === 'in_progress') foreach ($order->components as $line) if ($line->product->track_lots) {
            $query=$line->product->lots();
            if($line->product_variant_id)$query->whereHas('stockItem.variant',fn($q)=>$q->where('product_variants.id',$line->product_variant_id));
            $lotOptions[$line->id] = $query->withSum(['movements as available' => fn ($q) => $q->where('location_id', $order->location_id)], 'quantity_delta')->having('available','>',0)->orderBy('expires_at')->get();
        }
        return view('manufacturing.show', compact('order','lotOptions'));
    }

    public function confirm(Request $request, ManufacturingOrder $order, ManufacturingService $service) { $this->authorizeOrder($request,$order); $service->confirm($order,$request->user()->id); return back()->with('success','Manufacturing order confirmed.'); }
    public function start(Request $request, ManufacturingOrder $order, ManufacturingService $service) { $this->authorizeOrder($request,$order); $service->start($order,$request->user()->id); return back()->with('success','Production started. Stock will be consumed only when production is completed.'); }
    public function complete(Request $request, ManufacturingOrder $order, ManufacturingService $service)
    {
        $this->authorizeOrder($request,$order);
        $data=$request->validate(['component_lots'=>['nullable','array'],'component_lots.*'=>['nullable','integer','exists:product_lots,id'],'serial_mode'=>['nullable','in:auto,manual'],'serial_prefix'=>['nullable','string','max:60','regex:/^[A-Za-z0-9._\-]+$/'],'manual_serials'=>['nullable','string','max:100000']]);
        $service->complete($order,$data['component_lots']??[],$request->user()->id,$data);
        return redirect()->route('manufacturing.show',$order)->with('success','Production completed. Materials were consumed and the finished lot was added to stock.');
    }
    public function cancel(Request $request, ManufacturingOrder $order, ManufacturingService $service)
    {
        $this->authorizeOrder($request,$order);
        $data=$request->validate(['cancellation_reason'=>['required','string','min:5','max:1000']]);
        $service->cancel($order,$data['cancellation_reason'],$request->user()->id);
        return redirect()->route('manufacturing.show',$order)->with('success','Manufacturing order cancelled. No stock was changed.');
    }
    private function authorizeOrder(Request $request, ManufacturingOrder $order):void { $this->authorizeLocation($request,$order->location_id); }
    private function authorizeLocation(Request $request,int $id):void { abort_unless($request->user()->all_locations || $request->user()->locations()->where('locations.id',$id)->exists(),403); }
}
