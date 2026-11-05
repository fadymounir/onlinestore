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
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->depositActions(),
            ...$this->refundActions(),
            ...$this->statusActions(),
            ActionGroup::make([
                EditAction::make()->label('تعديل الملاحظات والعنوان'),
            ])->label('المزيد'),
        ];
    }

    /**
     * Reviewing the transfer the customer attached. Only a deposit order shows
     * these, and only until the deposit has been accepted — after that the
     * figures are part of the order's financial record.
     *
     * @return array<int, Action>
     */
    private function depositActions(): array
    {
        /** @var Order $order */
        $order = $this->getRecord();

        if (! $order->isDeposit() || $order->depositApproved()) {
            return [];
        }

        return [
            Action::make('approveDeposit')
                ->label('اعتماد العربون')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('اعتماد عربون الطلب')
                ->modalDescription(fn (): string => $order->depositCoversTotal()
                    ? 'تأكيد استلام قيمة الطلب كاملة '.number_format($order->depositAmount(), 2).' ج.م. '
                        .'سيصبح الطلب «مدفوع» ولا يتبقى شيء للتحصيل عند الاستلام.'
                    : 'تأكيد استلام '.number_format($order->depositAmount(), 2).' ج.م. '
                        .'سيصبح الطلب «مدفوع جزئياً» ويبقى '
                        .number_format($order->remainingAmount(), 2).' ج.م للتحصيل عند الاستلام.')
                ->modalSubmitActionLabel('اعتماد')
                ->modalCancelActionLabel('إلغاء')
                // Nothing to accept until the customer has actually uploaded one.
                ->disabled(blank($order->deposit_receipt_path))
                ->action(function () use ($order): void {
                    $order->approveDeposit($this->currentAdmin());

                    Notification::make()
                        ->title('تم اعتماد العربون — يمكنك الآن تأكيد الطلب')
                        ->success()
                        ->send();
                }),

            Action::make('rejectDeposit')
                ->label('رفض الإيصال')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => filled($order->deposit_receipt_path))
                ->modalHeading('رفض إيصال التحويل')
                ->modalDescription('سيظهر السبب للعميل في صفحة الطلب ويمكنه رفع إيصال آخر.')
                ->modalSubmitActionLabel('رفض')
                ->modalCancelActionLabel('إلغاء')
                ->schema([
                    Textarea::make('reason')
                        ->label('سبب الرفض')
                        ->placeholder('مثال: المبلغ المحوَّل أقل من قيمة العربون.')
                        ->rows(2)
                        ->required(),
                ])
                ->action(function (array $data) use ($order): void {
                    $order->rejectDeposit($data['reason'], $this->currentAdmin());

                    Notification::make()
                        ->title('تم رفض الإيصال وإبلاغ العميل في صفحة الطلب')
                        ->warning()
                        ->send();
                }),
        ];
    }

    /**
     * Paying back an order that was called off after the customer had already
     * transferred. Appears only while the order actually owes money, so it
     * leaves the page the moment the refund is recorded.
     *
     * @return array<int, Action>
     */
    private function refundActions(): array
    {
        /** @var Order $order */
        $order = $this->getRecord();

        if (! $order->awaitingRefund()) {
            return [];
        }

        $owed = $order->refundableAmount();

        return [
            Action::make('recordRefund')
                ->label('تسجيل استرداد المبلغ')
                ->icon('heroicon-o-receipt-refund')
                ->color('warning')
                ->modalHeading('استرداد مبلغ الطلب')
                ->modalDescription('المستحق للعميل '.number_format($owed, 2).' ج.م. '
                    .'سجّل التحويل بعد إرساله فعلياً — سيظهر للعميل في صفحة الطلب.')
                ->modalSubmitActionLabel('تسجيل الاسترداد')
                ->modalCancelActionLabel('إلغاء')
                ->schema([
                    TextInput::make('amount')
                        ->label('المبلغ المسترد')
                        ->numeric()
                        ->suffix('ج.م')
                        ->default($owed)
                        ->minValue(0.01)
                        // Never more than the store took. Less is legitimate:
                        // a restocking cut, or a settlement with the customer.
                        ->maxValue($owed)
                        ->required(),

                    TextInput::make('reference')
                        ->label('رقم عملية التحويل')
                        ->placeholder('رقم العملية من البنك أو المحفظة')
                        ->maxLength(100),

                    Textarea::make('note')
                        ->label('ملاحظة')
                        ->placeholder('مثال: تحويل إنستاباي على نفس رقم هاتف العميل.')
                        ->rows(2)
                        ->maxLength(255),
                ])
                ->action(function (array $data) use ($order): void {
                    $amount = (float) $data['amount'];

                    $order->recordRefund(
                        $amount,
                        $data['reference'] ?? null,
                        $data['note'] ?? null,
                        $this->currentAdmin(),
                    );

                    Notification::make()
                        ->title('تم تسجيل استرداد '.number_format($amount, 2).' ج.م للعميل')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function currentAdmin(): ?Admin
    {
        $admin = Auth::guard('admin')->user();

        return $admin instanceof Admin ? $admin : null;
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
                // Order::transitionTo() refuses this move outright; disabling
                // the button says why instead of throwing at the admin.
                ->disabled($this->blockedByDeposit($order, $target))
                ->tooltip($this->blockedByDeposit($order, $target)
                    ? 'اعتمد عربون التحويل أولاً.'
                    : null)
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
                    $order->transitionTo($target, $data['note'] ?? null, $this->currentAdmin());

                    Notification::make()
                        ->title('تم تحديث حالة الطلب إلى «'.$target->label().'»')
                        ->success()
                        ->send();
                }))
            ->all();
    }

    /** A deposit order cannot be confirmed while its transfer is unreviewed. */
    private function blockedByDeposit(Order $order, OrderStatus $target): bool
    {
        return $target === OrderStatus::Confirmed
            && $order->isDeposit()
            && ! $order->depositApproved();
    }

    private function confirmationFor(OrderStatus $target): string
    {
        /** @var Order $order */
        $order = $this->getRecord();

        if ($target === OrderStatus::Delivered && $order->isDeposit()) {
            return 'سيتم تسجيل الطلب كمُسلَّم ومدفوع بالكامل. '.$order->deliveryNote().'.';
        }

        if ($target === OrderStatus::Cancelled && $order->paidAmount() > 0) {
            return 'سيتم إلغاء الطلب، ويصبح المبلغ المدفوع '
                .number_format($order->paidAmount(), 2).' ج.م مستحقاً للاسترداد — '
                .'سجّله من زر «تسجيل استرداد المبلغ» بعد تحويله للعميل.';
        }

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
