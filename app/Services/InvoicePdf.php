<?php

namespace App\Services;

use App\Models\SalesInvoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdf
{
    public function bytes(SalesInvoice $invoice): string
    {
        $invoice->loadMissing(['customer','lines.product','installments','payments']);

        return Pdf::loadView('invoices.print', [
            'invoice' => $invoice,
            'pdfMode' => true,
        ])->setPaper('a4')->output();
    }

    public function filename(SalesInvoice $invoice): string
    {
        return str_replace('/', '-', $invoice->invoice_number).'.pdf';
    }
}
