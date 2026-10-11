<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Admin;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->statusActions(),
            ActionGroup::make([
                EditAction::make()->label('تعديل الملاحظات والعنوان'),
            ])->label('المزيد'),
        ];
    }

    /**
     * One button per legally reachable status, straight from the enum, so the
     * UI can never offer a move the domain forbids (delivered → pending).
     *
     * @return array<int, Action>
     */
    private function statusActions(): array
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return collect($order->status->allowedNext())
            ->map(fn (OrderStatus $target): Action => Action::make('moveTo'.$target->value)
                ->label($target->label())
                ->icon($target->icon())
                ->color($target->color())
                ->requiresConfirmation()
                ->modalHeading('تغيير حالة الطلب إلى: '.$target->label())
                ->modalDescription($this->confirmationFor($target))
                ->modalSubmitActionLabel('تأكيد')
                ->modalCancelActionLabel('إلغاء')
                ->schema([
                    Textarea::make('note')
                        ->label($target === OrderStatus::Cancelled ? 'سبب الإلغاء' : 'ملاحظة')
                        ->rows(2)
                        // A cancellation with no reason is useless a week later.
                        ->required(in_array($target, [OrderStatus::Cancelled, OrderStatus::Returned], true)),
                ])
                ->action(function (array $data) use ($order, $target): void {
                    $admin = Auth::guard('admin')->user();

                    $order->transitionTo(
                        $target,
                        $data['note'] ?? null,
                        $admin instanceof Admin ? $admin : null,
                    );

                    Notification::make()
                        ->title('تم تحديث حالة الطلب إلى «'.$target->label().'»')
                        ->success()
                        ->send();
                }))
            ->all();
    }

    private function confirmationFor(OrderStatus $target): string
    {
        return match ($target) {
            OrderStatus::Confirmed => 'سيتم تأكيد الطلب والبدء في تجهيزه.',
            OrderStatus::Processing => 'الطلب قيد التجهيز في المخزن.',
            OrderStatus::Shipped => 'الطلب خرج مع مندوب الشحن.',
            OrderStatus::Delivered => 'سيتم تسجيل الطلب كمُسلَّم، وتحديد حالة الدفع كمدفوع (الدفع عند الاستلام).',
            OrderStatus::Cancelled => 'سيتم إلغاء الطلب. لا يمكن التراجع عن هذا الإجراء.',
            OrderStatus::Returned => 'سيتم تسجيل الطلب كمرتجع. لا يمكن التراجع عن هذا الإجراء.',
            OrderStatus::Pending => '',
        };
    }
}
