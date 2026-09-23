<?php
namespace App\Http\Controllers;

use App\Http\Requests\SaveInvoiceSchemeRequest;
use App\Http\Requests\SaveInvoiceLayoutRequest;
use App\Models\InvoiceLayout;
use App\Models\InvoiceScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class InvoiceSettingController extends Controller
{
    public function index()
    {
        return view('business.invoice-settings', ['schemes'=>InvoiceScheme::orderByDesc('is_default')->orderBy('name')->get(), 'layouts'=>InvoiceLayout::with(['posLocations','saleLocations'])->orderByDesc('is_default')->orderBy('name')->get()]);
    }
    public function createLayout() { return view('business.invoice-layout-form',['layout'=>new InvoiceLayout]); }
    public function editLayout(InvoiceLayout $invoiceLayout) { return view('business.invoice-layout-form',['layout'=>$invoiceLayout->load(['labels','options'])]); }
    public function storeLayout(SaveInvoiceLayoutRequest $request): RedirectResponse
    {
        $this->persistLayout(new InvoiceLayout,$request);
        return redirect()->route('business.invoice-settings.index',['tab'=>'layouts'])->with('status','Invoice layout added successfully.');
    }
    public function updateLayout(SaveInvoiceLayoutRequest $request, InvoiceLayout $invoiceLayout): RedirectResponse
    {
        $this->persistLayout($invoiceLayout,$request);
        return redirect()->route('business.invoice-settings.index',['tab'=>'layouts'])->with('status','Invoice layout updated successfully.');
    }
    public function store(SaveInvoiceSchemeRequest $request): RedirectResponse
    {
        $this->persist(new InvoiceScheme, $request->validated());
        return back()->with('status','Invoice scheme added successfully.');
    }
    public function update(SaveInvoiceSchemeRequest $request, InvoiceScheme $invoiceScheme): RedirectResponse
    {
        $this->persist($invoiceScheme, $request->validated());
        return back()->with('status','Invoice scheme updated successfully.');
    }
    public function destroy(InvoiceScheme $invoiceScheme): RedirectResponse
    {
        if ($invoiceScheme->is_default) return back()->with('error','The default invoice scheme cannot be deleted.');
        if ($invoiceScheme->locations()->exists()) return back()->with('error','This invoice scheme is assigned to a business location.');
        $invoiceScheme->delete();
        return back()->with('status','Invoice scheme deleted successfully.');
    }
    private function persist(InvoiceScheme $scheme, array $data): void
    {
        DB::transaction(function () use ($scheme, $data) {
            // Format 2 has the year as its default prefix, for example 2026-0000.
            $data['prefix'] = trim((string) ($data['prefix'] ?? '')) ?: ($data['format'] === 'year_number' ? now()->year.'-' : '#');
            $makeDefault = (bool)($data['is_default'] ?? false) || ! InvoiceScheme::where('is_default',true)->exists();
            if ($makeDefault) InvoiceScheme::where('is_default',true)->when($scheme->exists, fn ($query) => $query->whereKeyNot($scheme->id))->update(['is_default'=>false]);
            $data['is_default'] = $makeDefault;
            if (! $scheme->exists) $data['current_number'] = $data['start_number'];
            elseif ($scheme->invoice_count === 0) $data['current_number'] = $data['start_number'];
            $scheme->fill($data)->save();
        });
    }
    private function persistLayout(InvoiceLayout $layout, SaveInvoiceLayoutRequest $request): void
    {
        $data=$request->validated(); $labels=$data['labels']??[]; $options=$data['options']??[]; unset($data['labels'],$data['options'],$data['logo']);
        if($request->hasFile('logo')){$file=$request->file('logo');$data['logo_path']='data:'.($file->getMimeType()?:'image/jpeg').';base64,'.base64_encode(file_get_contents($file->getRealPath()));}
        $data['is_default']=(bool)($data['is_default']??false)||!InvoiceLayout::where('is_default',true)->exists();
        $data['logo_path']=$data['logo_path']??$layout->logo_path;
        DB::transaction(function()use($layout,$data,$labels,$options){if($data['is_default'])InvoiceLayout::where('is_default',true)->when($layout->exists,fn($q)=>$q->whereKeyNot($layout->id))->update(['is_default'=>false]);$layout->fill($data)->save();$layout->labels()->delete();$layout->labels()->createMany(collect($labels)->filter(fn($v)=>$v!==null&&$v!=='')->map(fn($v,$k)=>['key'=>$k,'value'=>$v])->values()->all());$layout->options()->delete();$layout->options()->createMany(collect($options)->map(fn($v,$k)=>['key'=>$k,'enabled'=>(bool)$v])->values()->all());});
    }
}
