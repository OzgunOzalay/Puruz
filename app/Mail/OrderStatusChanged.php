<?php

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public OrderStatus $status,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->status->getLabel().' · '.$this->order->number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-status-changed');
    }
}
