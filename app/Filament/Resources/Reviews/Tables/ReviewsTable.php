<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(40),

                TextColumn::make('user.name')
                    ->label('العميل')
                    ->searchable()
                    ->description(fn (Review $record): string => $record->isVerifiedPurchase()
                        ? 'شراء موثّق'
                        : 'غير موثّق'),

                TextColumn::make('rating')
                    ->label('التقييم')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('comment')
                    ->label('التقييم')
                    ->wrap()
                    ->limit(70)
                    ->tooltip(fn (Review $record): ?string => $record->comment)
                    ->placeholder('بلا نص'),

                IconColumn::make('is_approved')
                    ->label('منشور')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('approver.name')
                    ->label('راجعه')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('النشر')
                    ->placeholder('الكل')
                    ->trueLabel('منشورة')
                    ->falseLabel('بانتظار المراجعة'),

                SelectFilter::make('rating')
                    ->label('التقييم')
                    ->options([
                        5 => '★★★★★',
                        4 => '★★★★',
                        3 => '★★★',
                        2 => '★★',
                        1 => '★',
                    ])
                    ->multiple(),

                SelectFilter::make('product')
                    ->label('المنتج')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('نشر')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Review $record): bool => ! $record->is_approved)
                    ->action(function (Review $record): void {
                        // Review::approve() stamps who approved it and when.
                        $record->approve(Auth::guard('admin')->user());

                        Notification::make()->title('تم نشر التقييم')->success()->send();
                    }),

                Action::make('unapprove')
                    ->label('إخفاء')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('إخفاء التقييم')
                    ->modalDescription('لن يظهر التقييم في صفحة المنتج.')
                    ->visible(fn (Review $record): bool => $record->is_approved)
                    ->action(function (Review $record): void {
                        $record->update([
                            'is_approved' => false,
                            'approved_at' => null,
                            'approved_by' => null,
                        ]);

                        Notification::make()->title('تم إخفاء التقييم')->success()->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveMany')
                        ->label('نشر المحدّد')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $admin = Auth::guard('admin')->user();
                            $count = 0;

                            foreach ($records as $review) {
                                if (! $review->is_approved) {
                                    $review->approve($admin);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("تم نشر {$count} تقييماً")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            // Pending reviews first — this screen is a moderation queue.
            ->defaultSort('is_approved')
            ->defaultGroup(null);
    }
}
