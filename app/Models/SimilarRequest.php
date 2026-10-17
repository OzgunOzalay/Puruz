<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer asking for a piece like a sold one ("Benzerini iste"). */
class SimilarRequest extends Model
{
    protected $fillable = ['product_id', 'message', 'contact', 'contact_type', 'status', 'admin_note', 'created_product_id', 'ip'];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['status' => RequestStatus::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function createdProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'created_product_id');
    }

    public function isEmail(): bool
    {
        return $this->contact_type === 'email';
    }

    /** "0532 123 45 67" for phones, the address itself for emails. */
    public function displayContact(): string
    {
        if ($this->isEmail() || ! preg_match('/^\+90(\d{3})(\d{3})(\d{2})(\d{2})$/', $this->contact, $m)) {
            return $this->contact;
        }

        return '0'.$m[1].' '.$m[2].' '.$m[3].' '.$m[4];
    }

    public function whatsappUrl(string $text = ''): ?string
    {
        return $this->isEmail() ? null : 'https://wa.me/'.ltrim($this->contact, '+').($text !== '' ? '?text='.rawurlencode($text) : '');
    }

    public function mailtoUrl(string $subject = ''): ?string
    {
        return $this->isEmail() ? 'mailto:'.$this->contact.($subject !== '' ? '?subject='.rawurlencode($subject) : '') : null;
    }
}
