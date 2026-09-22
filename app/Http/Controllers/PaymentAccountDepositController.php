<?php
namespace App\Http\Controllers;
use App\Models\{PaymentAccount,PaymentAccountDeposit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PaymentAccountDepositController extends Controller {
    public function store(Request $request) {
        $data=$request->validate(['request_id'=>'required|uuid','account_id'=>'required|exists:payment_accounts,id','from_account_id'=>'nullable|different:account_id|exists:payment_accounts,id','amount'=>['required','numeric','gt:0','regex:/^\d{1,12}(\.\d{1,2})?$/'],'date'=>'required|date_format:Y-m-d\TH:i','note'=>'nullable|string|max:5000']);
        DB::transaction(function() use($data){
            if(PaymentAccountDeposit::where('request_id',$data['request_id'])->exists()) return;
            $ids=array_filter([$data['account_id'],$data['from_account_id']]);
            $accounts=PaymentAccount::whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $target=$accounts->get($data['account_id']); $source=$data['from_account_id']?$accounts->get($data['from_account_id']):null;
            if(!$target?->is_active || ($source && !$source->is_active)) abort(422,'Select active accounts.');
            if($source && PaymentAccountTransferController::cents($source->current_balance)<PaymentAccountTransferController::cents($data['amount'])) abort(422,'The source account has insufficient funds.');
            PaymentAccountDeposit::create(['request_id'=>$data['request_id'],'account_id'=>$target->id,'from_account_id'=>$source?->id,'amount'=>$data['amount'],'deposited_at'=>str_replace('T',' ',$data['date']).':00','note'=>$data['note']??null,'created_by'=>auth()->id()]);
            DB::update('UPDATE payment_accounts SET current_balance=current_balance+?,updated_at=? WHERE id=?',[$data['amount'],now(),$target->id]);
            if($source) DB::update('UPDATE payment_accounts SET current_balance=current_balance-?,updated_at=? WHERE id=?',[$data['amount'],now(),$source->id]);
        });
        return response()->json(['message'=>'Deposit completed successfully.']);
    }
}
