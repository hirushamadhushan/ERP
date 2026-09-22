<?php
namespace App\Http\Controllers;
use App\Http\Requests\SavePaymentAccountRequest;
use App\Http\Requests\SavePaymentAccountTypeRequest;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PaymentAccountController extends Controller
{
    public function book(PaymentAccount $paymentAccount)
    {
        $paymentAccount->load(['type','subType','creator']);
        $opening=PaymentAccountTransferController::cents($paymentAccount->opening_balance);
        $entries=collect([[
            'date'=>\Carbon\Carbon::parse($paymentAccount->opening_balance_at ?? $paymentAccount->created_at),
            'description'=>'Opening Balance','debit'=>max(0,$opening),'credit'=>max(0,-$opening),
            'creator'=>$paymentAccount->creator?->name ?? 'System','note'=>'','transfer'=>null,
        ]]);
        $transfers=\App\Models\PaymentAccountTransfer::with(['source','destination','creator'])
            ->where('from_account_id',$paymentAccount->id)->orWhere('to_account_id',$paymentAccount->id)->orderBy('id')->get();
        foreach ($transfers as $transfer) {
            $outgoing=$transfer->from_account_id == $paymentAccount->id;
            $amount=PaymentAccountTransferController::cents($transfer->amount);
            $entries->push(['date'=>$transfer->transferred_at,'description'=>'Fund Transfer '.($outgoing?'to '.$transfer->destination->name:'from '.$transfer->source->name),
                'debit'=>$outgoing?0:$amount,'credit'=>$outgoing?$amount:0,'creator'=>$transfer->creator?->name ?? 'System',
                'note'=>$transfer->note,'transfer'=>$transfer]);
        }
        $deposits=\App\Models\PaymentAccountDeposit::with(['account','source','creator'])
            ->where(fn($query)=>$query->where('account_id',$paymentAccount->id)->orWhere('from_account_id',$paymentAccount->id))->orderBy('id')->get();
        foreach($deposits as $deposit) {
            $target=$deposit->account_id == $paymentAccount->id;
            $entries->push(['date'=>$deposit->deposited_at,'description'=>$target ? 'Deposit'.($deposit->source?' from '.$deposit->source->name:'') : 'Deposit to '.$deposit->account->name,
                'debit'=>$target?PaymentAccountTransferController::cents($deposit->amount):0,'credit'=>$target?0:PaymentAccountTransferController::cents($deposit->amount),
                'creator'=>$deposit->creator?->name??'System','note'=>$deposit->note,'transfer'=>null]);
        }
        $balance=0;
        $entries=$entries->sortBy('date')->values()->map(function ($entry) use (&$balance) {
            $balance+=$entry['debit']-$entry['credit'];
            return $entry+['balance'=>$balance];
        });
        return view('payment-accounts.book', ['account'=>$paymentAccount,'entries'=>$entries]);
    }

    public function updateOpeningBalance(\Illuminate\Http\Request $request, PaymentAccount $paymentAccount)
    {
        $data = $request->validate([
            'amount'=>['required','numeric','regex:/^-?\d{1,12}(\.\d{1,2})?$/'],
            'date'=>['required','date_format:Y-m-d\TH:i'],
        ]);
        DB::transaction(function () use ($paymentAccount, $data) {
            $account = PaymentAccount::whereKey($paymentAccount->id)->lockForUpdate()->firstOrFail();
            // Let the database perform decimal arithmetic without binary floating-point rounding.
            DB::update('UPDATE payment_accounts SET current_balance = current_balance - opening_balance + ?, opening_balance = ?, opening_balance_at = ?, updated_at = ? WHERE id = ?', [
                $data['amount'], $data['amount'], str_replace('T', ' ', $data['date']).':00', now(), $account->id,
            ]);
        });
        return $this->result('Opening balance updated successfully.');
    }
    public function index()
    {
        return view('payment-accounts.index', [
            'accounts'=>PaymentAccount::with(['type','subType','details','creator'])->latest()->get(),
            'types'=>PaymentAccountType::with('children')->whereNull('parent_id')->orderBy('name')->get(),
        ]);
    }
    public function typeOptions(): \Illuminate\Http\JsonResponse
    {
        return response()->json(PaymentAccountType::with('children:id,parent_id,name')->whereNull('parent_id')->orderBy('name')->get(['id','name']));
    }
    public function store(SavePaymentAccountRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->persist(new PaymentAccount, $request);
        return $this->result('Payment account added successfully.');
    }
    public function update(SavePaymentAccountRequest $request, PaymentAccount $paymentAccount): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->persist($paymentAccount, $request);
        return $this->result('Payment account updated successfully.');
    }
    public function destroy(PaymentAccount $paymentAccount): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if (\App\Models\PaymentAccountTransfer::where('from_account_id',$paymentAccount->id)->orWhere('to_account_id',$paymentAccount->id)->exists()) return $this->failure('Accounts with fund transfers cannot be deleted.');
        $paymentAccount->delete();
        return $this->result('Payment account deleted successfully.');
    }
    public function close(PaymentAccount $paymentAccount): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $paymentAccount->update(['is_active'=>false]);
        return $this->result('Payment account closed successfully.');
    }
    public function storeType(SavePaymentAccountTypeRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        PaymentAccountType::create($request->validated());
        return $this->result('Account type added successfully.');
    }
    public function updateType(SavePaymentAccountTypeRequest $request, PaymentAccountType $paymentAccountType): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $paymentAccountType->update($request->validated());
        return $this->result('Account type updated successfully.');
    }
    public function destroyType(PaymentAccountType $paymentAccountType): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if ($paymentAccountType->children()->exists() || $paymentAccountType->accounts()->exists()) return $this->failure('This account type is linked to an account or sub account type and cannot be deleted.');
        $paymentAccountType->delete();
        return $this->result('Account type deleted successfully.');
    }
    private function persist(PaymentAccount $account, SavePaymentAccountRequest $request): void
    {
        $data = $request->validated(); $details = $data['details'] ?? []; unset($data['details']);
        if (filled($data['payment_account_sub_type_id'] ?? null)) {
            $subType = PaymentAccountType::findOrFail($data['payment_account_sub_type_id']);
            abort_unless((int) $subType->parent_id === (int) ($data['payment_account_type_id'] ?? 0), 422, 'The selected account subtype does not belong to the selected account type.');
        }
        DB::transaction(function () use ($account, $data, $details) {
            if ($account->exists) {
                $account = PaymentAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
                DB::update('UPDATE payment_accounts SET current_balance = current_balance - opening_balance + ? WHERE id = ?', [$data['opening_balance'], $account->id]);
            }
            $data['is_active'] = (bool) ($data['is_active'] ?? ($account->exists ? $account->is_active : true));
            if (! $account->exists) { $data['created_by'] = auth()->id(); $data['current_balance'] = $data['opening_balance']; }
            $account->fill($data)->save();
            $account->details()->delete();
            $account->details()->createMany(collect($details)->take(2)->filter(fn ($detail) => filled($detail['label'] ?? null) && filled($detail['value'] ?? null))->values()->map(fn ($detail, $index) => ['label'=>$detail['label'], 'value'=>$detail['value'], 'display_order'=>$index + 1])->all());
        });
    }
    private function result(string $message): RedirectResponse|\Illuminate\Http\JsonResponse { return request()->expectsJson() ? response()->json(['message'=>$message]) : back()->with('status', $message); }
    private function failure(string $message): RedirectResponse|\Illuminate\Http\JsonResponse { return request()->expectsJson() ? response()->json(['message'=>$message], 422) : back()->with('error', $message); }
}
