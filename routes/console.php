<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use App\Models\DeliveryConsignmentProof;

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
