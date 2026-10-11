<?php

namespace App\Filament\Resources\Attributes\RelationManagers;

use App\Enums\AttributeType;
use App\Models\AttributeValue;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'القيم';

    protected static ?string $modelLabel = 'قيمة';

    protected static ?string $pluralModelLabel = 'القيم';

    private function isColour(): bool
    {
        return $this->getOwnerRecord()->type === AttributeType::Color;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')
                    ->label('القيمة')
                    ->required()
                    ->maxLength(255)
                    ->placeholder($this->isColour() ? 'أحمر' : 'XL')
                    ->unique(
                        table: 'attribute_values',
                        column: 'value',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule) => $rule->where('attribute_id', $this->getOwnerRecord()->id),
                    ),

                ColorPicker::make('color_hex')
                    ->label('اللون')
                    ->hex()
                    ->visible(fn (): bool => $this->isColour())
                    ->required(fn (): bool => $this->isColour())
                    ->helperText('يظهر كدائرة ملوّنة في صفحة المنتج.'),

                TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->integer()
                    ->minValue(0)
                    ->default(0),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->emptyStateHeading('لا قيم بعد')
            ->emptyStateDescription('أضف القيم التي يتغيّر بها المنتج، مثل المقاسات أو الألوان.')
            ->columns([
                ColorColumn::make('color_hex')
                    ->label('اللون')
                    ->visible(fn (): bool => $this->isColour()),

                TextColumn::make('value')
                    ->label('القيمة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('variants_count')
                    ->label('مستخدمة في')
                    ->counts('variants')
                    ->badge()
                    ->color('gray')
                    ->suffix(' متغيّر'),

                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->label('إضافة قيمة'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Model $record): void {
                        // The pivot cascades, which would quietly strip this
                        // value out of live variants and corrupt their labels.
                        if ($record->variants()->exists()) {
                            Notification::make()
                                ->title('لا يمكن حذف القيمة')
                                ->body('مستخدمة في '.$record->variants()->count().' متغيّر. احذف تلك المتغيّرات أولاً.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
