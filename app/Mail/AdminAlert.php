<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Something about an order needs a human to look at it (e.g. a payment that failed verification). */
class AdminAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Dikkat: '.$this->order->number.' kontrol edilmeli');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.admin-alert');
    }
}
