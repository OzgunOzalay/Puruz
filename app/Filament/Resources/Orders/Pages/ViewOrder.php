<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'Sipariş '.$this->getRecord()->number;
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderActions::ship(),
            OrderActions::changeStatus(),
            ActionGroup::make([
                OrderActions::note(),
                OrderActions::resendTrackingLink(),
                Action::make('tracking')
                    ->label('Müşteri takip sayfası')
                    ->icon('heroicon-o-map')
                    ->url(fn (Order $record) => $record->trackingUrl(), shouldOpenInNewTab: true),
                Action::make('print')
                    ->label('Paketleme fişi yazdır')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Order $record) => route('admin.orders.print', $record), shouldOpenInNewTab: true),
                OrderActions::refund(),
            ])->label('Diğer')->button()->color('gray'),
        ];
    }
}
