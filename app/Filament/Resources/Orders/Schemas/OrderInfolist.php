<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\DepositStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ملخص الطلب')
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('رقم الطلب')
                            ->weight(FontWeight::Bold)
                            ->copyable(),

                        TextEntry::make('status')
                            ->label('حالة الطلب')
                            ->badge()
                            ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                            ->color(fn (OrderStatus $state): string => $state->color())
                            ->icon(fn (OrderStatus $state): string => $state->icon()),

                        TextEntry::make('payment_status')
                            ->label('حالة الدفع')
                            ->badge()
                            ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                            ->color(fn (PaymentStatus $state): string => $state->color()),

                        TextEntry::make('payment_method')
                            ->label('طريقة الدفع')
                            ->formatStateUsing(fn (PaymentMethod $state): string => $state->label())
                            ->helperText(fn (Order $record): ?string => $record->isDeposit()
                                ? $record->deliveryNote()
                                : null),

                        TextEntry::make('placed_at')
                            ->label('تاريخ الطلب')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—'),

                        TextEntry::make('delivered_at')
                            ->label('تاريخ التوصيل')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(3),

                Grid::make(2)
                    ->schema([
                        Section::make('العميل')
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('الاسم')
                                    ->weight(FontWeight::Medium),

                                TextEntry::make('user.email')
                                    ->label('البريد الإلكتروني')
                                    ->copyable(),

                                TextEntry::make('user.phone')
                                    ->label('هاتف الحساب')
                                    ->copyable()
                                    ->placeholder('—'),

                                TextEntry::make('orders_count')
                                    ->label('عدد طلباته')
                                    ->state(fn (Order $record): int => $record->user?->orders()->count() ?? 0)
                                    ->badge(),
                            ]),

                        Section::make('عنوان التوصيل')
                            ->description('نسخة محفوظة وقت الطلب — تعديل العميل لعنوانه لا يغيّرها.')
                            ->schema([
                                TextEntry::make('recipient_name')
                                    ->label('المستلم'),

                                TextEntry::make('phone')
                                    ->label('هاتف التوصيل')
                                    ->copyable(),

                                TextEntry::make('alternate_phone')
                                    ->label('هاتف بديل')
                                    ->copyable()
                                    ->placeholder('—'),

                                TextEntry::make('full_address')
                                    ->label('العنوان')
                                    ->state(fn (Order $record): string => collect([
                                        $record->street,
                                        $record->building ? "عقار {$record->building}" : null,
                                        $record->floor ? "الدور {$record->floor}" : null,
                                        $record->apartment ? "شقة {$record->apartment}" : null,
                                        $record->city_name,
                                        $record->governorate_name,
                                    ])->filter()->implode('، ')),

                                TextEntry::make('landmark')
                                    ->label('علامة مميّزة')
                                    ->placeholder('—'),
                            ]),
                    ]),

                Section::make('البنود')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label('المنتج')
                                    ->weight(FontWeight::Medium),

                                TextEntry::make('variant_label')
                                    ->label('التركيبة')
                                    ->badge()
                                    ->placeholder('—'),

                                TextEntry::make('sku')
                                    ->label('SKU')
                                    ->copyable()
                                    ->placeholder('—'),

                                TextEntry::make('unit_price')
                                    ->label('سعر الوحدة')
                                    ->money('EGP'),

                                TextEntry::make('quantity')
                                    ->label('الكمية'),

                                TextEntry::make('line_total')
                                    ->label('الإجمالي')
                                    ->money('EGP')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('availability')
                                    ->label('المنتج بالكتالوج')
                                    ->badge()
                                    // product_id is nullOnDelete; the snapshot above
                                    // still reads fine, so say so rather than 404.
                                    ->state(fn (OrderItem $record): string => $record->product_id === null
                                        ? 'محذوف من الكتالوج'
                                        : 'موجود')
                                    ->color(fn (string $state): string => $state === 'موجود' ? 'success' : 'warning'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('الإجماليات')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('إجمالي البنود')
                            ->money('EGP'),

                        TextEntry::make('discount_total')
                            ->label('الخصم')
                            ->money('EGP')
                            ->color('danger')
                            ->helperText(fn (Order $record): ?string => $record->coupon_code
                                ? "كوبون {$record->coupon_code}"
                                : null),

                        TextEntry::make('shipping_total')
                            ->label('الشحن')
                            ->money('EGP')
                            ->helperText(fn (Order $record): ?string => (float) $record->shipping_total === 0.0
                                ? 'شحن مجاني'
                                : null),

                        TextEntry::make('grand_total')
                            ->label('الإجمالي المطلوب')
                            ->money('EGP')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color('primary'),

                        TextEntry::make('remaining_amount')
                            ->label('المتبقي للتحصيل')
                            ->state(fn (Order $record): float => $record->remainingAmount())
                            ->money('EGP')
                            ->weight(FontWeight::Bold)
                            ->color(fn (Order $record): string => $record->remainingAmount() > 0 ? 'warning' : 'success')
                            ->helperText(fn (Order $record): string => $record->deliveryNote())
                            ->columnSpanFull(),
                    ])
                    ->columns(4),

                Section::make('العربون المقدّم')
                    ->description('تحويل بنكي مُسبق من العميل — لا يُؤكَّد الطلب قبل اعتماده.')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Order $record): bool => $record->isDeposit())
                    ->schema([
                        TextEntry::make('deposit_status')
                            ->label('حالة العربون')
                            ->badge()
                            ->formatStateUsing(fn (DepositStatus $state): string => $state->label())
                            ->color(fn (DepositStatus $state): string => $state->color())
                            ->icon(fn (DepositStatus $state): string => $state->icon())
                            ->placeholder('—'),

                        TextEntry::make('deposit_amount')
                            ->label('المبلغ المحوَّل')
                            ->money('EGP')
                            ->weight(FontWeight::Bold)
                            ->helperText(fn (Order $record): ?string => $record->deposit_percent
                                ? rtrim(rtrim(number_format((float) $record->deposit_percent, 2), '0'), '.').'% من الإجمالي'
                                : null),

                        TextEntry::make('deposit_reference')
                            ->label('رقم عملية التحويل')
                            ->copyable()
                            ->placeholder('لم يُذكر'),

                        TextEntry::make('deposit_submitted_at')
                            ->label('تاريخ رفع الإيصال')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—'),

                        TextEntry::make('depositReviewer.name')
                            ->label('رُوجع بواسطة')
                            ->placeholder('لم يُراجع بعد'),

                        TextEntry::make('deposit_reviewed_at')
                            ->label('تاريخ المراجعة')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—'),

                        TextEntry::make('deposit_rejection_reason')
                            ->label('سبب الرفض')
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (Order $record): bool => filled($record->deposit_rejection_reason)),

                        // The receipt itself: images preview inline, a PDF is a
                        // link — Filament cannot render one in place.
                        ImageEntry::make('deposit_receipt_path')
                            ->label('إيصال التحويل')
                            ->disk(Order::RECEIPT_DISK)
                            ->height(260)
                            ->extraImgAttributes(['class' => 'rounded-lg'])
                            ->columnSpanFull()
                            ->visible(fn (Order $record): bool => filled($record->deposit_receipt_path)
                                && ! str_ends_with(strtolower((string) $record->deposit_receipt_path), '.pdf')),

                        TextEntry::make('receipt_link')
                            ->label('ملف الإيصال')
                            ->state(fn (Order $record): string => filled($record->deposit_receipt_path)
                                ? 'فتح الإيصال في تبويب جديد'
                                : 'لم يُرفع إيصال')
                            ->url(fn (Order $record): ?string => $record->depositReceiptUrl())
                            ->openUrlInNewTab()
                            ->color(fn (Order $record): string => filled($record->deposit_receipt_path) ? 'primary' : 'gray')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('استرداد المبلغ')
                    ->description('الطلب أُلغي أو ارتجع بعد أن دفع العميل — المبلغ يُرد إليه.')
                    ->icon('heroicon-o-receipt-refund')
                    ->visible(fn (Order $record): bool => $record->awaitingRefund() || $record->isRefunded())
                    ->schema([
                        TextEntry::make('refund_due')
                            ->label('المستحق للعميل')
                            ->state(fn (Order $record): float => $record->refundableAmount())
                            ->money('EGP')
                            ->weight(FontWeight::Bold)
                            ->color('warning')
                            ->helperText('لم يُسجَّل بعد — سجّله من زر «تسجيل استرداد المبلغ» بعد التحويل.')
                            ->visible(fn (Order $record): bool => $record->awaitingRefund()),

                        TextEntry::make('refund_amount')
                            ->label('المبلغ المسترد')
                            ->money('EGP')
                            ->weight(FontWeight::Bold)
                            ->color('success')
                            ->visible(fn (Order $record): bool => $record->isRefunded()),

                        TextEntry::make('refunded_at')
                            ->label('تاريخ الاسترداد')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—')
                            ->visible(fn (Order $record): bool => $record->isRefunded()),

                        TextEntry::make('refundIssuer.name')
                            ->label('نُفِّذ بواسطة')
                            ->placeholder('النظام')
                            ->visible(fn (Order $record): bool => $record->isRefunded()),

                        TextEntry::make('refund_reference')
                            ->label('رقم عملية التحويل')
                            ->copyable()
                            ->placeholder('لم يُذكر')
                            ->visible(fn (Order $record): bool => $record->isRefunded()),

                        TextEntry::make('refund_note')
                            ->label('ملاحظة الاسترداد')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->visible(fn (Order $record): bool => filled($record->refund_note)),
                    ])
                    ->columns(3),

                Section::make('ملاحظات')
                    ->schema([
                        TextEntry::make('customer_note')
                            ->label('ملاحظة العميل')
                            ->placeholder('لا توجد'),

                        TextEntry::make('admin_note')
                            ->label('ملاحظة داخلية')
                            ->placeholder('لا توجد'),

                        TextEntry::make('cancel_reason')
                            ->label('سبب الإلغاء')
                            ->placeholder('—')
                            ->visible(fn (Order $record): bool => filled($record->cancel_reason)),
                    ])
                    ->columns(2)
                    ->collapsed(fn (Order $record): bool => blank($record->customer_note) && blank($record->admin_note)),

                Section::make('سجل الحالات')
                    ->description('كل تغيير مع وقته ومَن نفّذه.')
                    ->schema([
                        RepeatableEntry::make('statusHistories')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('الوقت')
                                    ->dateTime('Y-m-d H:i'),

                                TextEntry::make('transition')
                                    ->label('التغيير')
                                    ->state(fn (OrderStatusHistory $record): string => ($record->from_status?->label() ?? 'طلب جديد')
                                        .' → '.$record->to_status->label())
                                    ->badge()
                                    ->color(fn (OrderStatusHistory $record): string => $record->to_status->color()),

                                TextEntry::make('admin.name')
                                    ->label('بواسطة')
                                    ->placeholder('النظام'),

                                TextEntry::make('note')
                                    ->label('ملاحظة')
                                    ->placeholder('—'),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible(),
            ]);
    }
}
