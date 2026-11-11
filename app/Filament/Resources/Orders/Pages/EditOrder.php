<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        // No DeleteAction: an order is a financial record.
        return [
            $this->repriceShippingAction(),
            ViewAction::make(),
        ];
    }

    /**
     * Prices the shipping once the governorate is known.
     *
     * An order placed while customer addresses were switched off has nowhere
     * to ship to and so no shipping on it. Filling the address in above gives
     * the shop a governorate; this turns that into money. Deliberately a
     * button and not a save hook — it moves the grand total, and that should
     * never be a side effect of correcting a street name.
     */
    private function repriceShippingAction(): Action
    {
        return Action::make('repriceShipping')
            ->label('احتساب الشحن من المحافظة')
            ->icon(Heroicon::OutlinedTruck)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('احتساب الشحن')
            ->modalDescription(function (): string {
                /** @var Order $order */
                $order = $this->record;
                $quote = $order->shippingQuote();

                if ($quote === null) {
                    return 'لا توجد منطقة شحن لهذه المحافظة، فلا يمكن احتساب الشحن.';
                }

                $new = round((float) $order->subtotal - (float) $order->discount_total + $quote, 2);

                return 'الشحن سيصبح '.number_format($quote, 2).' ج.م،'
                    .' وإجمالي الطلب '.number_format((float) $order->grand_total, 2)
                    .' ← '.number_format($new, 2).' ج.م.';
            })
            ->modalSubmitActionLabel('احتساب وحفظ')
            // Hidden once the parcel is gone or the order is closed: the
            // figures are settled by then.
            ->visible(fn (): bool => ! $this->record->status->isFinal())
            ->action(function (): void {
                /** @var Order $order */
                $order = $this->record;

                if (! $order->repriceShipping()) {
                    Notification::make()
                        ->title('لا توجد منطقة شحن لهذه المحافظة')
                        ->body('اضبط منطقة شحن للمحافظة أولاً، ثم أعد المحاولة.')
                        ->warning()
                        ->send();

                    return;
                }

                $this->fillForm();

                Notification::make()
                    ->title('تم احتساب الشحن')
                    ->body('الشحن '.number_format((float) $order->shipping_total, 2)
                        .' ج.م، والإجمالي '.number_format((float) $order->grand_total, 2).' ج.م.')
                    ->success()
                    ->send();
            });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
