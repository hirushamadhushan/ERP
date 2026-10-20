<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('sales_invoices')
            ->whereIn('status', ['posted', 'partial', 'paid'])
            ->orderBy('id')
            ->chunkById(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    if ((float) $invoice->paid_amount === 0.0 && (float) $invoice->balance_due === 0.0) {
                        DB::table('sales_invoices')->where('id', $invoice->id)
                            ->update(['balance_due' => $invoice->total_amount]);
                    }

                    if (DB::table('sales_invoice_journal_items')->where('sales_invoice_id', $invoice->id)->exists()) {
                        continue;
                    }

                    $now = now();
                    $items = [
                        ['account_code' => '121000', 'account_name' => 'Account Receivable', 'label' => $invoice->invoice_number, 'debit' => $invoice->total_amount, 'credit' => 0],
                        ['account_code' => '400000', 'account_name' => 'Product Sales', 'label' => 'Sales revenue', 'debit' => 0, 'credit' => $invoice->untaxed_amount],
                    ];
                    if ((float) $invoice->tax_amount > 0) {
                        $items[] = ['account_code' => '220000', 'account_name' => 'Tax Payable', 'label' => 'Sales tax', 'debit' => 0, 'credit' => $invoice->tax_amount];
                    }
                    DB::table('sales_invoice_journal_items')->insert(array_map(
                        fn ($item) => $item + ['sales_invoice_id' => $invoice->id, 'created_at' => $now, 'updated_at' => $now],
                        $items
                    ));
                }
            });
    }

    public function down(): void
    {
        // Historical journal entries may now include real payments and must be retained.
    }
};
