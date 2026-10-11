<?php

namespace App\Filament\Resources\ShippingZones\RelationManagers;

use App\Models\Governorate;
use App\Models\ShippingZone;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class GovernoratesRelationManager extends RelationManager
{
    protected static string $relationship = 'governorates';

    protected static ?string $title = 'المحافظات';

    protected static ?string $modelLabel = 'محافظة';

    protected static ?string $pluralModelLabel = 'المحافظات';

    /** Governorates are seeded; here they are only assigned to zones. */
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading('لا محافظات في هذه المنطقة')
            ->emptyStateDescription('أضف محافظات حتى يُحسب لها سعر شحن.')
            ->columns([
                TextColumn::make('name')
                    ->label('المحافظة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_en')
                    ->label('بالإنجليزية')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('cities_count')
                    ->label('المدن')
                    ->counts('cities')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('addresses_count')
                    ->label('عناوين العملاء')
                    ->counts('addresses')
                    ->badge()
                    ->color('gray'),

                ToggleColumn::make('is_active')
                    ->label('مُفعّلة'),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label('إضافة محافظات')
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([
                        Select::make('governorate_ids')
                            ->label('المحافظات')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->preload()
                            // Offer every governorate not already in this zone,
                            // flagging the ones that would move from another.
                            ->options(fn (): array => Governorate::with('shippingZone')
                                ->where(fn ($q) => $q->whereNull('shipping_zone_id')
                                    ->orWhere('shipping_zone_id', '!=', $this->getOwnerRecord()->id))
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn (Governorate $g): array => [
                                    $g->id => $g->shippingZone
                                        ? "{$g->name}  (حالياً: {$g->shippingZone->name})"
                                        : "{$g->name}  (بلا منطقة)",
                                ])
                                ->all()),
                    ])
                    ->action(function (array $data): void {
                        /** @var ShippingZone $zone */
                        $zone = $this->getOwnerRecord();

                        $moved = Governorate::whereIn('id', $data['governorate_ids'])
                            ->update(['shipping_zone_id' => $zone->id]);

                        Notification::make()
                            ->title("تم نقل {$moved} محافظة إلى «{$zone->name}»")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('detach')
                    ->label('إزالة من المنطقة')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إزالة المحافظة من المنطقة')
                    ->modalDescription('ستبقى المحافظة بلا سعر شحن حتى تُضاف إلى منطقة أخرى.')
                    ->action(function (Governorate $record): void {
                        $record->update(['shipping_zone_id' => null]);

                        Notification::make()
                            ->title("أُزيلت «{$record->name}» من المنطقة")
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('detachMany')
                        ->label('إزالة المحدّد')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            Governorate::whereIn('id', $records->pluck('id'))
                                ->update(['shipping_zone_id' => null]);

                            Notification::make()
                                ->title('أُزيلت '.$records->count().' محافظة')
                                ->warning()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}
