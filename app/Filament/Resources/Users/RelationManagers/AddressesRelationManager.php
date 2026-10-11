<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Address;
use App\Models\City;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'العناوين';

    protected static ?string $modelLabel = 'عنوان';

    protected static ?string $pluralModelLabel = 'العناوين';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('المستلم')
                    ->schema([
                        TextInput::make('label')
                            ->label('وسم العنوان')
                            ->placeholder('المنزل / العمل')
                            ->maxLength(255),

                        TextInput::make('recipient_name')
                            ->label('اسم المستلم')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->required()
                            ->tel()
                            ->maxLength(20),

                        TextInput::make('alternate_phone')
                            ->label('هاتف بديل')
                            ->tel()
                            ->maxLength(20),
                    ])
                    ->columns(2),

                Section::make('الموقع')
                    ->schema([
                        Select::make('governorate_id')
                            ->label('المحافظة')
                            ->relationship('governorate', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            // Cities belong to a governorate; clear a stale pick.
                            ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),

                        Select::make('city_id')
                            ->label('المدينة')
                            ->relationship(
                                name: 'city',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                    ->where('governorate_id', $get('governorate_id') ?? 0),
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('اختر المحافظة أولاً.'),

                        TextInput::make('street')
                            ->label('الشارع')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('building')->label('العقار')->maxLength(255),
                        TextInput::make('floor')->label('الدور')->maxLength(50),
                        TextInput::make('apartment')->label('الشقة')->maxLength(50),

                        TextInput::make('landmark')
                            ->label('علامة مميّزة')
                            ->placeholder('أمام / بجوار…')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Toggle::make('is_default')
                            ->label('العنوان الافتراضي'),
                    ])
                    ->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('street')
            ->emptyStateHeading('لا عناوين محفوظة')
            ->columns([
                TextColumn::make('label')
                    ->label('الوسم')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('recipient_name')
                    ->label('المستلم')
                    ->description(fn (Address $record): string => $record->phone),

                TextColumn::make('full_address')
                    ->label('العنوان')
                    ->state(fn (Address $record): string => $record->fullAddress())
                    ->wrap(),

                IconColumn::make('is_default')
                    ->label('افتراضي')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('إضافة عنوان'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('is_default', 'desc');
    }
}
