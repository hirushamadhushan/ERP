<?php

namespace App\Services;

use App\Models\InvoiceScheme;
use App\Models\Contact;
use App\Models\SalesInvoiceCreditNote;
use App\Models\SalesInvoice;
use App\Models\PaymentAccount;
use App\Models\TaxRate;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class SalesInvoiceService
{
    public function save(?SalesInvoice $invoice, array $data, int $userId): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            if ($invoice && $invoice->status !== 'draft') throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be edited.']);
            $lines = $data['lines']; unset($data['lines']);
            $invoice ??= new SalesInvoice(['created_by' => $userId, 'status' => 'draft']);
            if (! $invoice->invoice_number) $invoice->invoice_number = $this->nextNumber($data['location_id'] ?? null);

            $calculated = $this->calculate($lines, $data['tax_display']);
            $invoice->fill($data + $calculated + ['balance_due'=>$calculated['total_amount']])->save();
            $invoice->lines()->delete();
            foreach ($calculated['lines'] as $order => $line) $invoice->lines()->create($line + ['sort_order' => $order]);
            $this->syncInstallments($invoice);
            return $invoice->load(['customer', 'lines.product', 'lines.taxRate']);
        });
    }

    public function post(SalesInvoice $invoice, int $userId): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $userId) {
            $invoice = SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== 'draft') throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be confirmed.']);
            if (! $invoice->lines()->where('line_type', 'product')->exists()) throw ValidationException::withMessages(['lines' => 'Add at least one product line.']);
            $customer=Contact::whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();
            app(CustomerBalanceService::class)->refresh($customer);
            if ($customer->credit_limit !== null && round((float)$customer->due_balance+(float)$invoice->total_amount,2) > (float)$customer->credit_limit) {
                throw ValidationException::withMessages(['credit_limit'=>'This invoice would exceed the customer credit limit.']);
            }
            $invoice->update(['status'=>'posted', 'posted_by'=>$userId, 'posted_at'=>now(), 'balance_due'=>$invoice->total_amount]);
            $invoice->journalItems()->delete();
            $accounts=$this->accounts();
            foreach ($invoice->installments()->orderBy('sequence')->get() as $installment) {
                $invoice->journalItems()->create(['account_code'=>$accounts['receivable'],'account_name'=>'Account Receivable','label'=>$invoice->invoice_number.' · Installment '.$installment->sequence,'due_date'=>$installment->due_date,'debit'=>$installment->amount,'credit'=>0]);
            }
            $invoice->journalItems()->create(['account_code'=>$accounts['revenue'],'account_name'=>'Product Sales','label'=>'Sales revenue','debit'=>0,'credit'=>$invoice->untaxed_amount]);
            if ((float)$invoice->tax_amount > 0) $invoice->journalItems()->create(['account_code'=>$accounts['tax'],'account_name'=>'Tax Payable','label'=>'Sales tax','debit'=>0,'credit'=>$invoice->tax_amount]);
            $invoice->activities()->create(['type'=>'system','message'=>'Invoice confirmed and posted.','created_by'=>$userId]);
            app(CustomerBalanceService::class)->refresh($customer);
            return $invoice;
        });
    }

    public function resetToDraft(SalesInvoice $invoice, int $userId): SalesInvoice
    {
        return DB::transaction(function () use ($invoice,$userId) {
            $invoice=SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $customer=Contact::whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();
            if ($invoice->payments()->exists() || $invoice->creditAllocations()->exists() || $invoice->creditNotes()->exists()) throw ValidationException::withMessages(['invoice'=>'An invoice with payments or credit notes cannot be reset to draft.']);
            $invoice->journalItems()->delete(); $invoice->update(['status'=>'draft','posted_by'=>null,'posted_at'=>null]);
            app(CustomerBalanceService::class)->refresh($customer);
            $invoice->activities()->create(['type'=>'system','message'=>'Invoice reset to draft.','created_by'=>$userId]); return $invoice;
        });
    }

    public function registerPayment(SalesInvoice $invoice, array $data, int $userId): SalesInvoice
    {
        return DB::transaction(function () use ($invoice,$data,$userId) {
            $invoice=SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (!in_array($invoice->status,['posted','partial'],true)) throw ValidationException::withMessages(['invoice'=>'Only an unpaid posted invoice can receive payment.']);
            $customer=Contact::whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();
            $amount=round((float)$data['amount'],2); if ($amount<=0 || $amount>(float)$invoice->balance_due) throw ValidationException::withMessages(['amount'=>'Payment must be greater than zero and cannot exceed the balance due.']);
            $account=PaymentAccount::whereKey($data['payment_account_id'])->lockForUpdate()->firstOrFail();
            if (! $account->is_active) throw ValidationException::withMessages(['payment_account_id'=>'Select an active payment account.']);
            $payment=$invoice->payments()->create(['payment_account_id'=>$account->id,'payment_number'=>'PAY/'.now()->year.'/'.\Illuminate\Support\Str::uuid(),'payment_date'=>$data['payment_date'],'amount'=>$amount,'method'=>$data['method']??null,'reference'=>$data['reference']??null,'note'=>$data['note']??null,'created_by'=>$userId]);
            $payment->update(['payment_number'=>'PAY/'.now()->year.'/'.str_pad((string)$payment->id,5,'0',STR_PAD_LEFT)]);
            $accountCode=$account->account_number ?: 'BANK';
            $invoice->journalItems()->create(['account_code'=>$accountCode,'account_name'=>$account->name,'label'=>$payment->payment_number,'debit'=>$amount,'credit'=>0]);
            $invoice->journalItems()->create(['account_code'=>$this->accounts()['receivable'],'account_name'=>'Account Receivable','label'=>$payment->payment_number,'debit'=>0,'credit'=>$amount]);
            $account->increment('current_balance',$amount); $paid=round((float)$invoice->paid_amount+$amount,2); $balance=round((float)$invoice->total_amount-$paid-(float)$invoice->credited_amount,2);
            $this->settleInstallments($invoice,$amount);
            $invoice->update(['paid_amount'=>$paid,'balance_due'=>$balance,'status'=>$balance<=0?'paid':'partial']);
            app(CustomerBalanceService::class)->refresh($customer);
            $invoice->activities()->create(['type'=>'payment','message'=>'Payment '.$payment->payment_number.' of '.number_format($amount,2).' Rs registered.','created_by'=>$userId]); return $invoice;
        });
    }

    public function applyCustomerCredit(SalesInvoice $invoice, int $userId): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $userId) {
            $invoice = SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! in_array($invoice->status, ['posted', 'partial'], true) || (float) $invoice->balance_due <= 0) {
                throw ValidationException::withMessages(['invoice' => 'Only an unpaid posted invoice can receive customer credit.']);
            }
            $customer = Contact::whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();
            $available = round((float) $customer->advance_balance, 2);
            if ($available <= 0) throw ValidationException::withMessages(['credit' => 'This customer has no available advance credit.']);
            $amount = min($available, round((float) $invoice->balance_due, 2));
            $invoice->creditAllocations()->create(['amount' => $amount, 'created_by' => $userId]);
            $accounts=$this->accounts();
            $invoice->journalItems()->create(['account_code'=>$accounts['advance'],'account_name'=>'Customer Advances','label'=>'Advance credit applied','debit'=>$amount,'credit'=>0]);
            $invoice->journalItems()->create(['account_code'=>$accounts['receivable'],'account_name'=>'Account Receivable','label'=>'Advance credit applied','debit'=>0,'credit'=>$amount]);
            $customer->forceFill(['advance_balance' => round($available - $amount, 2)])->save();
            $paid = round((float) $invoice->paid_amount + $amount, 2);
            $balance = round((float) $invoice->total_amount - $paid - (float) $invoice->credited_amount, 2);
            $this->settleInstallments($invoice,$amount);
            $invoice->update(['paid_amount' => $paid, 'balance_due' => $balance, 'status' => $balance <= 0 ? 'paid' : 'partial']);
            app(CustomerBalanceService::class)->refresh($customer);
            $invoice->activities()->create(['type' => 'payment', 'message' => 'Applied '.number_format($amount, 2).' Rs from customer advance credit.', 'created_by' => $userId]);
            return $invoice;
        });
    }

    public function createCreditNote(SalesInvoice $invoice, array $data, int $userId): SalesInvoiceCreditNote
    {
        return DB::transaction(function () use ($invoice, $data, $userId) {
            $invoice = SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! in_array($invoice->status, ['posted', 'partial', 'paid', 'credited'], true)) {
                throw ValidationException::withMessages(['invoice' => 'Confirm the invoice before issuing a credit note.']);
            }
            $amount = round((float) $data['amount'], 2);
            $remaining = round((float) $invoice->total_amount - (float) $invoice->credited_amount, 2);
            if ($amount <= 0 || $amount > $remaining) throw ValidationException::withMessages(['amount' => 'Credit amount must be greater than zero and cannot exceed the uncredited invoice total.']);
            $customer = Contact::whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();
            $note = $invoice->creditNotes()->create(['credit_number' => 'RINV/'.now()->year.'/'.\Illuminate\Support\Str::uuid(), 'credit_date'=>today(), 'status'=>'posted', 'amount' => $amount, 'reason' => $data['reason'], 'created_by' => $userId]);
            $note->update(['credit_number' => 'RINV/'.now()->year.'/'.str_pad((string) $note->id, 5, '0', STR_PAD_LEFT)]);
            $applied = min($amount, round((float) $invoice->balance_due, 2));
            $excess = round($amount - $applied, 2);
            if ($excess > 0) $customer->forceFill(['advance_balance' => round((float) $customer->advance_balance + $excess, 2)])->save();
            $remainingToAllocate=$amount;$revenue=0;$tax=0;
            foreach($invoice->lines()->where('line_type','product')->orderBy('sort_order')->get() as $line){
                if($remainingToAllocate<=0)break;
                $already=(float)\App\Models\SalesInvoiceCreditNoteLine::where('sales_invoice_line_id',$line->id)->sum('total');
                $available=round((float)$line->total-$already,2);if($available<=0)continue;
                $take=min($remainingToAllocate,$available);$ratio=$take/max((float)$line->total,0.01);
                $base=round((float)$line->subtotal*$ratio,2);$lineTax=round($take-$base,2);
                $note->lines()->create(['sales_invoice_line_id'=>$line->id,'description'=>$line->description,'quantity'=>round((float)$line->quantity*$ratio,4),'unit_price'=>$line->unit_price,'tax_rate'=>$line->tax_rate,'subtotal'=>$base,'tax_amount'=>$lineTax,'total'=>$take]);
                $revenue=round($revenue+$base,2);$tax=round($tax+$lineTax,2);$remainingToAllocate=round($remainingToAllocate-$take,2);
            }
            if($remainingToAllocate>0)throw ValidationException::withMessages(['amount'=>'Credit amount exceeds the remaining creditable invoice lines.']);
            $note->update(['untaxed_amount'=>$revenue,'tax_amount'=>$tax]);
            $accounts=$this->accounts();
            if ($revenue > 0) $note->journalItems()->create(['account_code' => $accounts['revenue'], 'account_name' => 'Product Sales', 'label' => $note->credit_number, 'debit' => $revenue, 'credit' => 0]);
            if ($tax > 0) $note->journalItems()->create(['account_code' => $accounts['tax'], 'account_name' => 'Tax Payable', 'label' => $note->credit_number, 'debit' => $tax, 'credit' => 0]);
            if ($applied > 0) $note->journalItems()->create(['account_code' => $accounts['receivable'], 'account_name' => 'Account Receivable', 'label' => $note->credit_number, 'debit' => 0, 'credit' => $applied]);
            if ($excess > 0) $note->journalItems()->create(['account_code' => $accounts['advance'], 'account_name' => 'Customer Advances', 'label' => $note->credit_number, 'debit' => 0, 'credit' => $excess]);
            $credited = round((float) $invoice->credited_amount + $amount, 2);
            $balance = round(max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount - $credited), 2);
            $this->settleInstallments($invoice,$applied);
            $status = $balance > 0 ? 'partial' : ((float) $invoice->paid_amount > 0 ? 'paid' : 'credited');
            $invoice->update(['credited_amount' => $credited, 'balance_due' => $balance, 'status' => $status]);
            app(CustomerBalanceService::class)->refresh($customer);
            $invoice->activities()->create(['type' => 'system', 'message' => 'Credit note '.$note->credit_number.' issued for '.number_format($amount, 2).' Rs. '.$data['reason'], 'created_by' => $userId]);
            return $note;
        });
    }

    private function calculate(array $lines, string $taxDisplay): array
    {
        $untaxed = $taxTotal = $grand = 0; $output = [];
        foreach ($lines as $line) {
            if ($line['line_type'] !== 'product') {
                $output[] = ['line_type'=>$line['line_type'], 'description'=>$line['description'], 'quantity'=>0, 'unit_price'=>0, 'tax_rate'=>0, 'subtotal'=>0, 'tax_amount'=>0, 'total'=>0];
                continue;
            }
            $rate = ($line['tax_rate_id'] ?? null) ? (float) TaxRate::findOrFail($line['tax_rate_id'])->amount : 0;
            $raw = round((float)$line['quantity'] * (float)$line['unit_price'], 2);
            $discountPercent = (float) ($line['discount_percent'] ?? 0);
            if ($discountPercent < 0 || $discountPercent > 100) {
                throw ValidationException::withMessages(['lines' => 'Discount must be between 0 and 100 percent.']);
            }
            $line['discount_percent'] = $discountPercent;
            $line['discount_amount'] = round($raw * $discountPercent / 100, 2);
            $raw = round($raw - $line['discount_amount'], 2);
            if ($taxDisplay === 'inclusive' && $rate > 0) { $subtotal = round($raw / (1 + $rate / 100), 2); $tax = $raw - $subtotal; }
            else { $subtotal = $raw; $tax = round($subtotal * $rate / 100, 2); }
            $total = $subtotal + $tax; $untaxed += $subtotal; $taxTotal += $tax; $grand += $total;
            $output[] = $line + ['tax_rate'=>$rate, 'subtotal'=>$subtotal, 'tax_amount'=>$tax, 'total'=>$total];
        }
        return ['untaxed_amount'=>$untaxed, 'tax_amount'=>$taxTotal, 'total_amount'=>$grand, 'lines'=>$output];
    }

    private function syncInstallments(SalesInvoice $invoice): void
    {
        $invoiceDate=Carbon::parse($invoice->invoice_date)->startOfDay();
        $term=trim((string)$invoice->payment_term);
        $schedule=[];

        if ($term==='30% Now, Balance 60 Days') {
            $later=$invoiceDate->copy()->addDays(60);
            $first=round((float)$invoice->total_amount*.30,2);
            $schedule=[[$invoiceDate,$first],[$later,round((float)$invoice->total_amount-$first,2)]];
        } else {
            $due=$invoice->due_date ? Carbon::parse($invoice->due_date) : $invoiceDate->copy();
            if ($term==='End of Following Month') $due=$invoiceDate->copy()->addMonthsNoOverflow(2)->endOfMonth()->subMonthNoOverflow();
            elseif ($term==='10 Days after End of Next Month') $due=$invoiceDate->copy()->addMonthNoOverflow()->endOfMonth()->addDays(10);
            elseif (preg_match('/(\d+)\s*days?/i',$term,$match)) $due=$invoiceDate->copy()->addDays((int)$match[1]);
            $schedule=[[$due,round((float)$invoice->total_amount,2)]];
        }

        $invoice->installments()->delete();
        foreach ($schedule as $index=>[$due,$amount]) $invoice->installments()->create(['sequence'=>$index+1,'due_date'=>$due->toDateString(),'amount'=>$amount,'settled_amount'=>0,'status'=>'pending']);
    }

    private function settleInstallments(SalesInvoice $invoice, float $amount): void
    {
        $remaining=round($amount,2);
        foreach ($invoice->installments()->orderBy('sequence')->lockForUpdate()->get() as $installment) {
            if ($remaining<=0) break;
            $open=round((float)$installment->amount-(float)$installment->settled_amount,2);
            if ($open<=0) continue;
            $applied=min($remaining,$open);
            $settled=round((float)$installment->settled_amount+$applied,2);
            $installment->update(['settled_amount'=>$settled,'status'=>$settled>=(float)$installment->amount?'paid':'partial']);
            $remaining=round($remaining-$applied,2);
        }
    }

    public function previewNumber(?int $locationId = null): string
    {
        return $this->nextNumber($locationId, false);
    }

    private function nextNumber(?int $locationId = null, bool $allocate = true): string
    {
        $scheme = $locationId
            ? InvoiceScheme::whereHas('locations', fn($query)=>$query->whereKey($locationId))->when($allocate, fn($query)=>$query->lockForUpdate())->first()
            : null;
        $scheme ??= InvoiceScheme::where('is_default', true)->when($allocate, fn($query)=>$query->lockForUpdate())->first();
        if (! $scheme) return 'INV/'.now()->year.'/'.str_pad((string)(SalesInvoice::count() + 1), 5, '0', STR_PAD_LEFT);
        $number = $scheme->current_number;
        if ($allocate) $scheme->increment('current_number');
        $prefix = $scheme->prefix ?: 'INV/'.now()->year.'/';
        return $prefix.str_pad((string)$number, $scheme->number_of_digits, '0', STR_PAD_LEFT);
    }

    private function accounts(): array
    {
        $settings=BusinessSetting::current();
        return ['receivable'=>$settings->invoice_receivable_account_code ?: '121000','revenue'=>$settings->invoice_revenue_account_code ?: '400000','tax'=>$settings->invoice_tax_account_code ?: '220000','advance'=>$settings->invoice_advance_account_code ?: '210000'];
    }
}
