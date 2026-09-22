<?php
namespace App\Http\Controllers;

use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentAccountTransferController extends Controller
{
    public function options() {
        return response()->json(PaymentAccount::where('is_active',true)->orderBy('name')->get(['id','name','account_number']));
    }

    public function store(Request $request) {
        $data=$request->validate([
            'request_id'=>'required|uuid',
            'from_account_id'=>'required|integer|exists:payment_accounts,id',
            'to_account_id'=>'required|integer|different:from_account_id|exists:payment_accounts,id',
            'amount'=>['required','numeric','gt:0','regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'date'=>['required','date_format:Y-m-d\TH:i'],
            'note'=>'nullable|string|max:5000',
            'document'=>'nullable|file|max:5120|mimes:pdf,csv,txt,zip,doc,docx,jpeg,jpg,png|extensions:pdf,csv,zip,doc,docx,jpeg,jpg,png',
        ]);
        $path=null;
        try {
            DB::transaction(function () use ($request,$data,&$path) {
                $accounts=PaymentAccount::whereIn('id',[$data['from_account_id'],$data['to_account_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $existing=PaymentAccountTransfer::where('request_id',$data['request_id'])->first();
                if ($existing) {
                    if ($existing->created_by !== auth()->id() || $existing->from_account_id != $data['from_account_id'] || $existing->to_account_id != $data['to_account_id'] || self::cents($existing->amount) !== self::cents($data['amount'])) {
                        throw ValidationException::withMessages(['request_id'=>'This transfer request was already used.']);
                    }
                    return;
                }
                $source=$accounts->get($data['from_account_id']);
                $destination=$accounts->get($data['to_account_id']);
                if (!$source?->is_active || !$destination?->is_active) throw ValidationException::withMessages(['from_account_id'=>'Select two active accounts.']);
                if (self::cents($source->current_balance)<self::cents($data['amount'])) throw ValidationException::withMessages(['amount'=>'The source account has insufficient funds.']);
                if ($request->hasFile('document')) {
                    $path=$request->file('document')->store('payment-transfers','local');
                    if (!$path) throw new \RuntimeException('The attachment could not be saved.');
                }
                PaymentAccountTransfer::create([
                    'request_id'=>$data['request_id'],'from_account_id'=>$source->id,'to_account_id'=>$destination->id,
                    'amount'=>$data['amount'],'transferred_at'=>str_replace('T',' ',$data['date']).':00',
                    'note'=>$data['note']??null,'document_path'=>$path,
                    'document_name'=>$request->file('document')?->getClientOriginalName(),'created_by'=>auth()->id(),
                ]);
                DB::update('UPDATE payment_accounts SET current_balance = current_balance - ?, updated_at = ? WHERE id = ?',[$data['amount'],now(),$source->id]);
                DB::update('UPDATE payment_accounts SET current_balance = current_balance + ?, updated_at = ? WHERE id = ?',[$data['amount'],now(),$destination->id]);
            });
        } catch (\Throwable $error) {
            if ($path) Storage::disk('local')->delete($path);
            throw $error;
        }
        return response()->json(['message'=>'Funds transferred successfully.']);
    }

    public function document(PaymentAccountTransfer $transfer) {
        abort_unless($transfer->document_path && Storage::disk('local')->exists($transfer->document_path),404);
        return Storage::disk('local')->download($transfer->document_path,$transfer->document_name);
    }

    public static function cents(string $amount): int {
        $negative=str_starts_with($amount,'-');
        [$whole,$fraction]=array_pad(explode('.',ltrim($amount,'-'),2),2,'');
        return ((int)$whole*100+(int)str_pad($fraction,2,'0'))*($negative?-1:1);
    }
}
