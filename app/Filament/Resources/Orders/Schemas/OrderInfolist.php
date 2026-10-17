<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\OrderStatusText;
use App\Models\Order;
use App\Support\Human;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (?int $state) => Money::format($state);

        return $schema
            ->columns(3)
            ->components([
                ViewEntry::make('next_step')->hiddenLabel()->view('filament.order-next-step')->columnSpanFull(),
                Grid::make(1)->columnSpan(2)->schema([
                    Section::make('Ürünler')->schema([
                        ViewEntry::make('items')->hiddenLabel()->view('filament.order-items'),
                    ]),
                    Section::make('Durum geçmişi')->collapsible()->schema([
                        ViewEntry::make('histories')->hiddenLabel()->view('filament.order-history'),
                    ]),
                    Section::make('Ödeme')->collapsible()->collapsed()->columns(3)->schema([
                        TextEntry::make('payment_id')->label('iyzico ödeme no')->placeholder('—')->copyable(),
                        TextEntry::make('paid_at')->label('Ödeme tarihi')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('installment')->label('Taksit')->placeholder('—'),
                        TextEntry::make('card')->label('Kart')
                            ->state(fn (Order $record) => trim(($record->card_association ?? '').' '.($record->card_family ?? '').($record->card_last_four ? ' ****'.$record->card_last_four : '')) ?: null)
                            ->placeholder('—'),
                        TextEntry::make('payment_error')->label('Son ödeme hatası')->placeholder('—')->color('danger'),
                        TextEntry::make('refunded_total')->label('İade edilen')->formatStateUsing($money),
                    ]),
                ]),

                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Özet')->schema([
                        TextEntry::make('status')->label('Durum')->formatStateUsing(fn (Order $record) => OrderStatusText::html($record))->html(),
                        TextEntry::make('payment_sentence')->label('Ödeme')
                            ->state(fn (Order $record) => $record->paid_at
                                ? collect(['Kart ile ödendi', Human::date($record->paid_at), $record->installment > 1 ? $record->installment.' taksit' : null])->filter()->implode(' · ')
                                : 'Henüz ödenmedi'),
                        TextEntry::make('total')->label('Toplam')->formatStateUsing($money)->weight('bold')->size('lg'),
                        TextEntry::make('source')->label('Kaynak')
                            ->state(fn (Order $record) => match (true) {
                                ! $record->utm_source => 'Doğrudan geldi',
                                $record->utm_source === 'instagram' => 'Instagram '.($record->utm_medium ?: '').'’dan geldi',
                                default => ucfirst($record->utm_source).'’dan geldi',
                            }),
                        TextEntry::make('cargo')->label('Kargo')
                            ->state(fn (Order $record) => $record->cargo_tracking_number ? $record->cargo_company?->getLabel().' · '.$record->cargo_tracking_number : null)
                            ->url(fn (Order $record) => $record->cargoTrackingUrl(), shouldOpenInNewTab: true)
                            ->visible(fn (Order $record) => (bool) $record->cargo_tracking_number),
                    ]),
                    Section::make('Müşteri')->schema([
                        TextEntry::make('customer_name')->label('Ad soyad'),
                        TextEntry::make('customer_email')->label('E-posta')->copyable()->url(fn (Order $record) => 'mailto:'.$record->customer_email),
                        TextEntry::make('customer_phone')->label('Telefon')->copyable()
                            ->url(fn (Order $record) => 'https://wa.me/'.ltrim($record->customer_phone, '+'), shouldOpenInNewTab: true)
                            ->helperText('WhatsApp’ta açmak için tıklayın'),
                        TextEntry::make('customer_note')->label('Müşteri notu')->placeholder('—')->color('warning'),
                        TextEntry::make('admin_note')->label('İç not')->placeholder('—'),
                        TextEntry::make('marketing_consent')->label('Duyuru izni')->formatStateUsing(fn (bool $state) => $state ? 'Evet' : 'Hayır'),
                    ]),
                    Section::make('Teslimat adresi')->visible(fn (Order $record) => $record->requires_shipping)->schema([
                        TextEntry::make('shipping_block')->hiddenLabel()
                            ->state(fn (Order $record) => $record->shipping_name."\n".$record->shipping_address."\n".$record->shipping_district.' / '.$record->shipping_city.($record->shipping_postal_code ? ' '.$record->shipping_postal_code : ''))
                            ->copyable()
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
                    Section::make('Fatura')->schema([
                        TextEntry::make('billing_block')->hiddenLabel()
                            ->state(fn (Order $record) => collect([
                                $record->billing_type === 'corporate' ? $record->billing_company : $record->billing_name,
                                $record->billing_type === 'corporate' ? 'VD: '.$record->billing_tax_office.' · VKN: '.$record->billing_tax_number : ($record->identity_number ? 'TCKN: '.$record->identity_number : null),
                                $record->billing_address,
                                trim($record->billing_district.' / '.$record->billing_city, ' /'),
                            ])->filter()->implode("\n"))
                            ->extraAttributes(['class' => 'whitespace-pre-line']),
                    ]),
                ]),
            ]);
    }
}
