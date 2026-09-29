<?php
namespace App\Services;

use App\Models\Contact;
use App\Models\CustomerPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerManagementService
{
    /** Records a due payment and its customer balance together, so a retry cannot double-collect money. */
    public function collectDue(Contact $customer, array $data, int $userId): CustomerPayment
    {
        return DB::transaction(function () use ($customer, $data, $userId) {
            $customer = Contact::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $existing = CustomerPayment::where('request_id', $data['request_id'])->first();
            if ($existing) return $existing;
            if ($customer->due_balance <= 0) throw ValidationException::withMessages(['amount' => 'This customer has no outstanding due balance.']);
            if ((float) $data['amount'] > (float) $customer->due_balance) throw ValidationException::withMessages(['amount' => 'Amount cannot exceed the current customer due.']);
            $payment = CustomerPayment::create(['request_id'=>$data['request_id'], 'contact_id'=>$customer->id, 'receipt_no'=>'CR-'.Str::upper((string) Str::ulid()), 'amount'=>$data['amount'], 'payment_method'=>$data['payment_method'], 'paid_at'=>$data['paid_at'], 'note'=>$data['note'] ?? null, 'created_by'=>$userId]);
            $customer->decrement('due_balance', $data['amount']);
            return $payment;
        });
    }
}
