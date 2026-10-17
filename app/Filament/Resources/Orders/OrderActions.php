<?php

namespace App\Filament\Resources\Orders;

use App\Enums\CargoCompany;
use App\Enums\OrderStatus;
use App\Filament\Support\MoneyInput;
use App\Mail\TrackingLinks;
use App\Models\Order;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\RefundService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Order actions shared by the order list and the order detail page. */
class OrderActions
{
    public static function changeStatus(): Action
    {
        return Action::make('changeStatus')
            ->label('Durumu değiştir')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (Order $record) => $record->status->allowedTransitions() !== [])
            ->schema(fn (Order $record) => [
                Select::make('status')
                    ->label('Yeni durum')
                    ->options(collect($record->status->allowedTransitions())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->getLabel()]))
                    ->required()
                    ->live(),
                Textarea::make('note')
                    ->label('Müşteriye not (isteğe bağlı)')
                    ->helperText('Takip sayfasında ve e-postada görünür.')
                    ->rows(2),
                Toggle::make('notify')
                    ->label('Müşteriye e-posta gönder')
                    ->default(true),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    app(OrderStatusService::class)->transition($record, OrderStatus::from($data['status']), $data['note'] ?? null, (bool) $data['notify'], auth()->user());
                    Notification::make()->success()->title('Sipariş durumu güncellendi')->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    public static function ship(): Action
    {
        return Action::make('ship')
            ->label('Kargoya ver')
            ->icon('heroicon-o-truck')
            ->color('primary')
            ->visible(fn (Order $record) => $record->requires_shipping && in_array($record->status, [OrderStatus::Paid, OrderStatus::Preparing], true))
            ->schema([
                Select::make('cargo_company')
                    ->label('Kargo firması')
                    ->options(CargoCompany::class)
                    ->default(CargoCompany::Yurtici)
                    ->required(),
                TextInput::make('tracking_number')
                    ->label('Takip numarası')
                    ->required()
                    ->maxLength(60),
                Textarea::make('note')->label('Müşteriye not (isteğe bağlı)')->rows(2),
                Toggle::make('notify')->label('Müşteriye e-posta gönder')->default(true),
            ])
            ->action(function (Order $record, array $data) {
                $company = $data['cargo_company'] instanceof CargoCompany ? $data['cargo_company'] : CargoCompany::from($data['cargo_company']);
                app(OrderStatusService::class)->ship($record, $company, $data['tracking_number'], $data['note'] ?? null, (bool) $data['notify'], auth()->user());
                Notification::make()->success()->title('Sipariş kargoya verildi')->send();
            });
    }

    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('İade / iptal')
            ->icon('heroicon-o-receipt-refund')
            ->color('danger')
            ->visible(fn (Order $record) => auth()->user()?->isAdmin() && $record->status->isPaid() && $record->refundableTotal() > 0)
            ->modalDescription('İade, ödemenin alındığı karta iyzico üzerinden yapılır. Aynı gün yapılan tam iadeler iptal olarak işlenir ve komisyon kesilmez.')
            ->schema(fn (Order $record) => [
                Radio::make('mode')
                    ->label('İade türü')
                    ->options([
                        'full' => 'Tamamını iade et ve siparişi iptal et ('.Money::format($record->refundableTotal()).')',
                        'partial' => 'Kısmi iade',
                    ])
                    ->default('full')
                    ->live()
                    ->required(),
                Select::make('line')
                    ->label('Kalem')
                    ->options(function () use ($record) {
                        $options = [];
                        foreach ($record->items as $item) {
                            if ($item->refundableAmount() > 0) {
                                $options['item-'.$item->id] = $item->displayName().' (en fazla '.Money::format($item->refundableAmount()).')';
                            }
                        }
                        if ($record->shipping_total - $record->shipping_refunded > 0) {
                            $options['shipping'] = 'Kargo ücreti (en fazla '.Money::format($record->shipping_total - $record->shipping_refunded).')';
                        }

                        return $options;
                    })
                    ->visible(fn (Get $get) => $get('mode') === 'partial')
                    ->required(fn (Get $get) => $get('mode') === 'partial'),
                MoneyInput::make('amount')
                    ->label('İade tutarı')
                    ->visible(fn (Get $get) => $get('mode') === 'partial')
                    ->required(fn (Get $get) => $get('mode') === 'partial'),
                Textarea::make('reason')->label('Sebep / müşteriye not')->rows(2),
                Toggle::make('restock')
                    ->label('Ürünleri stoğa / kontenjana geri ekle')
                    ->default(true)
                    ->visible(fn (Get $get) => $get('mode') === 'full'),
                Toggle::make('notify')
                    ->label('Müşteriye e-posta gönder')
                    ->default(true)
                    ->visible(fn (Get $get) => $get('mode') === 'full'),
            ])
            ->requiresConfirmation()
            ->action(function (Order $record, array $data) {
                $service = app(RefundService::class);

                try {
                    if ($data['mode'] === 'full') {
                        $service->refundAll($record, $data['reason'] ?? null, (bool) ($data['restock'] ?? true), (bool) ($data['notify'] ?? true), auth()->user(), request()->ip());
                    } else {
                        $item = $data['line'] === 'shipping' ? null : $record->items->firstWhere('id', (int) str_replace('item-', '', $data['line']));
                        $service->refundPartial($record, $item, (int) $data['amount'], $data['reason'] ?? null, auth()->user(), request()->ip());
                    }
                    Notification::make()->success()->title('İade işlemi tamamlandı')->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title('İade yapılamadı')->body($e->getMessage())->persistent()->send();
                }
            });
    }

    public static function resendTrackingLink(): Action
    {
        return Action::make('resendTracking')
            ->label('Takip linkini e-postala')
            ->icon('heroicon-o-envelope')
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record) => $record->customer_email.' adresine takip linki gönderilecek.')
            ->action(function (Order $record) {
                Mail::to($record->customer_email)->send(new TrackingLinks($record));
                Notification::make()->success()->title('E-posta gönderildi')->send();
            });
    }

    public static function note(): Action
    {
        return Action::make('note')
            ->label('İç not')
            ->icon('heroicon-o-pencil-square')
            ->fillForm(fn (Order $record) => ['admin_note' => $record->admin_note])
            ->schema([Textarea::make('admin_note')->label('Sadece yöneticilerin gördüğü not')->rows(4)])
            ->action(function (Order $record, array $data) {
                $record->forceFill(['admin_note' => $data['admin_note']])->save();
                Notification::make()->success()->title('Not kaydedildi')->send();
            });
    }

    public static function bulkPreparing(Collection $records): void
    {
        $service = app(OrderStatusService::class);
        $done = 0;

        foreach ($records as $order) {
            if ($order->status === OrderStatus::Paid && $order->requires_shipping) {
                $service->transition($order, OrderStatus::Preparing, null, true, auth()->user());
                $done++;
            }
        }

        Notification::make()->success()->title($done.' sipariş hazırlanıyor olarak işaretlendi')->send();
    }

    public static function exportCsv(Collection $records): StreamedResponse
    {
        $records->load('items');

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Sipariş', 'Tarih', 'Durum', 'Müşteri', 'E-posta', 'Telefon', 'İl', 'İlçe', 'Adres', 'Ürünler', 'Ara toplam', 'İndirim', 'Kargo', 'Toplam', 'İade', 'Kargo firması', 'Takip no', 'Kaynak'], ';');
            foreach ($records as $order) {
                fputcsv($out, [
                    $order->number,
                    $order->created_at->format('d.m.Y H:i'),
                    $order->status->getLabel(),
                    $order->customer_name,
                    $order->customer_email,
                    $order->customer_phone,
                    $order->shipping_city,
                    $order->shipping_district,
                    $order->shipping_address,
                    $order->items->map(fn ($i) => $i->quantity.'x '.$i->displayName())->implode(' | '),
                    Money::toDecimal($order->subtotal),
                    Money::toDecimal($order->discount_total),
                    Money::toDecimal($order->shipping_total),
                    Money::toDecimal($order->total),
                    Money::toDecimal($order->refunded_total),
                    $order->cargo_company?->getLabel(),
                    $order->cargo_tracking_number,
                    $order->utm_source,
                ], ';');
            }
            fclose($out);
        }, 'siparisler-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
