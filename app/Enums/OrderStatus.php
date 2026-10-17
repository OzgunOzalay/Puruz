<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case PendingPayment = 'pending_payment';
    case PaymentFailed = 'payment_failed';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Ödeme bekleniyor',
            self::PaymentFailed => 'Ödeme başarısız',
            self::Paid => 'Ödeme alındı',
            self::Preparing => 'Hazırlanıyor',
            self::Shipped => 'Kargoya verildi',
            self::Delivered => 'Teslim edildi',
            self::Confirmed => 'Rezervasyon onaylandı',
            self::Completed => 'Tamamlandı',
            self::Cancelled => 'İptal edildi',
            self::Refunded => 'İade edildi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingPayment => 'gray',
            self::PaymentFailed, self::Cancelled => 'danger',
            self::Paid => 'warning',
            self::Preparing, self::Confirmed => 'info',
            self::Shipped => 'primary',
            self::Delivered, self::Completed => 'success',
            self::Refunded => 'gray',
        };
    }

    /** Statuses that mean the customer has actually paid. */
    public static function paidStatuses(): array
    {
        return [self::Paid, self::Preparing, self::Shipped, self::Delivered, self::Confirmed, self::Completed];
    }

    public function isPaid(): bool
    {
        return in_array($this, self::paidStatuses(), true);
    }

    /** Next statuses an admin may move an order to by hand. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            // Cancelling a paid order always goes through a refund, see RefundService.
            self::Paid => [self::Preparing, self::Shipped, self::Confirmed, self::Completed],
            self::Preparing => [self::Shipped, self::Completed],
            self::Shipped => [self::Delivered, self::Completed],
            self::Delivered => [self::Completed],
            self::Confirmed => [self::Completed],
            default => [],
        };
    }

    /** Customer-facing explanation shown on the tracking page and in emails. */
    public function customerMessage(): string
    {
        return match ($this) {
            self::PendingPayment => 'Siparişiniz oluşturuldu, ödeme adımı tamamlanmadı.',
            self::PaymentFailed => 'Ödeme tamamlanamadı. Tekrar deneyebilirsiniz.',
            self::Paid => 'Ödemeniz alındı, siparişiniz sıraya girdi.',
            self::Preparing => 'Ürünleriniz özenle hazırlanıyor ve paketleniyor.',
            self::Shipped => 'Siparişiniz kargoya verildi.',
            self::Delivered => 'Siparişiniz teslim edildi. Keyifle kullanın!',
            self::Confirmed => 'Atölye rezervasyonunuz onaylandı. Görüşmek üzere!',
            self::Completed => 'Siparişiniz tamamlandı. Teşekkür ederiz!',
            self::Cancelled => 'Siparişiniz iptal edildi.',
            self::Refunded => 'Ödemeniz iade edildi. Bankanıza bağlı olarak hesabınıza yansıması birkaç gün sürebilir.',
        };
    }
}
