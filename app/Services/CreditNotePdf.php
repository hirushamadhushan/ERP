<?php

namespace App\Services;

use App\Models\SalesInvoiceCreditNote;
use Barryvdh\DomPDF\Facade\Pdf;

class CreditNotePdf
{
    public function bytes(SalesInvoiceCreditNote $creditNote): string
    {
        $creditNote->loadMissing(['invoice.customer','lines']);

        return Pdf::loadView('invoices.credit-note', [
            'invoice' => $creditNote->invoice,
            'creditNote' => $creditNote,
            'pdfMode' => true,
        ])->setPaper('a4')->output();
    }

    public function filename(SalesInvoiceCreditNote $creditNote): string
    {
        return str_replace('/', '-', $creditNote->credit_number).'.pdf';
    }
}
