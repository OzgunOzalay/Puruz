<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\SimilarRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SimilarPieceReady extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SimilarRequest $similar, public Product $piece) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'İstediğiniz parça hazır · '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.similar-piece-ready');
    }
}
