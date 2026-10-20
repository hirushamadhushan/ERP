<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalesInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $invoiceNumber,
        public string $emailSubject,
        public string $messageText,
        public string $invoicePdf,
    ) {}

    public function build(): static
    {
        return $this->subject($this->emailSubject)
            ->html(nl2br(e($this->messageText)))
            ->attachData($this->invoicePdf, str_replace('/', '-', $this->invoiceNumber).'.pdf', ['mime' => 'application/pdf']);
    }
}
