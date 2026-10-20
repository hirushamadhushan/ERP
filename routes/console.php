<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use App\Models\DeliveryConsignmentProof;
use App\Models\SalesInvoice;
use App\Services\SalesInvoiceService;
use App\Models\Contact;
use App\Services\CustomerBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('delivery:purge-expired-proofs', function () {
    $count=0;
    DeliveryConsignmentProof::whereNotNull('deleted_at')->whereNull('purged_at')->where('retention_until','<=',now())
        ->orderBy('id')->chunkById(100,function($proofs)use(&$count){foreach($proofs as $proof){if(Storage::disk('local')->delete($proof->path)){$proof->update(['purged_at'=>now()]);$count++;}}});
    $this->info("Purged {$count} expired delivery proof file(s); audit metadata was retained.");
})->purpose('Delete POD files whose legal retention period has expired');

Schedule::command('delivery:purge-expired-proofs')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('invoices:auto-post', function (SalesInvoiceService $service) {
    $posted=0;$failed=0;
    SalesInvoice::where('status','draft')->where('auto_post',true)->whereDate('invoice_date','<=',today())
        ->orderBy('id')->chunkById(100,function($invoices)use($service,&$posted,&$failed){
            foreach($invoices as $invoice){
                try{$service->post($invoice,(int)$invoice->created_by);$posted++;}
                catch(\Throwable $exception){$failed++;Log::error('Automatic invoice posting failed',['invoice_id'=>$invoice->id,'exception'=>$exception]);}
            }
        });
    $this->info("Automatically posted {$posted} invoice(s); {$failed} failed.");
    return $failed ? 1 : 0;
})->purpose('Post due draft invoices that have auto-post enabled');

Schedule::command('invoices:auto-post')->hourly()->withoutOverlapping();

Artisan::command('contacts:refresh-balances', function (CustomerBalanceService $service) {
    $count=0;
    Contact::whereIn('type',['customer','both'])->orderBy('id')->chunkById(100,function($contacts)use($service,&$count){
        foreach($contacts as $contact) DB::transaction(function()use($contact,$service,&$count){
            $locked=Contact::whereKey($contact->id)->lockForUpdate()->firstOrFail();
            $service->refresh($locked);$count++;
        });
    });
    $this->info("Refreshed balances and last-sale dates for {$count} customer(s).");
})->purpose('Recalculate customer receivables from opening balances, receipts and confirmed invoices');
