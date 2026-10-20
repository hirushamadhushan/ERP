<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\Schema;

class CustomerBalanceService
{
    // Called inside the transaction that changes the customer's receivables.
    public function refresh(Contact $customer): void
    {
        if (!in_array($customer->type,['customer','both'],true)) return;
        $invoices=SalesInvoice::where('customer_id',$customer->id)->where('status','!=','draft');
        $receipts=Schema::hasTable('customer_payments') ? (float)$customer->customerPayments()->sum('amount') : 0;
        $hasInvoices=Schema::hasTable('sales_invoices');
        $openingDue=max(0,round((float)$customer->opening_balance-$receipts,2));
        $customer->forceFill([
            'due_balance'=>round($openingDue+($hasInvoices ? (float)(clone $invoices)->sum('balance_due') : 0),2),
            'last_sale_at'=>$hasInvoices ? (clone $invoices)->max('invoice_date') : null,
        ])->save();
    }
}
