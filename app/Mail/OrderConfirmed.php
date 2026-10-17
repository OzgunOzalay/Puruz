<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Ics;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once the payment is confirmed. Carries the tracking link, and the distance sales
 * contract as a PDF so the customer has it on a durable medium as the regulation requires.
 */
class OrderConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Siparişiniz alındı · '.$this->order->number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-confirmed');
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->order->contract_snapshot) {
            $attachments[] = Attachment::fromData(
                fn () => Pdf::loadView('contracts.pdf', ['order' => $this->order])->output(),
                'mesafeli-satis-sozlesmesi-'.$this->order->number.'.pdf',
            )->withMime('application/pdf');
        }

        if ($ics = Ics::forOrder($this->order)) {
            $attachments[] = Attachment::fromData(fn () => $ics, 'atolye-'.$this->order->number.'.ics')
                ->withMime('text/calendar');
        }

        return $attachments;
    }
}
