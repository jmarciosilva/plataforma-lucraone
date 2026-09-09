<?php

namespace App\Modules\Reporting\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Resumo periódico de vendas — o envio agendado que a F2.4 deixou pendente.
 */
class SalesSummaryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $estabelecimento,
        public string $periodo,
        public array $totais,
        public array $topProdutos,
        public array $estoque,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "resumo de vendas · {$this->estabelecimento} · {$this->periodo}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.sales-summary');
    }
}
