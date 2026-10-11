<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات التصنيف')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم التصنيف')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('المعرّف في الرابط')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9-]+$/']),

                        Select::make('parent_id')
                            ->label('التصنيف الأب')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'name',
                                // A category may not be its own parent, nor sit
                                // under one of its own descendants.
                                modifyQueryUsing: function (Builder $query, ?Model $record) {
                                    if ($record) {
                                        $query->whereNotIn('id', [
                                            $record->getKey(),
                                            ...self::descendantIds($record),
                                        ]);
                                    }

                                    return $query;
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('تصنيف رئيسي (بلا أب)')
                            ->helperText('اتركه فارغاً ليكون تصنيفاً رئيسياً في القائمة.'),

                        TextInput::make('sort_order')
                            ->label('ترتيب الظهور')
                            ->integer()
                            ->minValue(0)
                            ->default(0),

                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('الصورة والحالة')
                    ->schema([
                        FileUpload::make('image')
                            ->label('صورة التصنيف')
                            ->image()
                            ->imageEditor()
                            ->directory('categories')
                            ->disk('public'),

                        Toggle::make('is_active')
                            ->label('مُفعّل')
                            ->default(true)
                            ->helperText('إيقافه يخفيه من الموقع مع كل منتجاته.'),
                    ])
                    ->columns(2),
            ]);
    }

    /** @return array<int, int> ids of every category beneath $category */
    private static function descendantIds(Model $category): array
    {
        $ids = [];
        $queue = $category->children()->pluck('id')->all();

        while ($queue !== []) {
            $id = array_shift($queue);
            $ids[] = $id;

            foreach (\App\Models\Category::where('parent_id', $id)->pluck('id') as $childId) {
                $queue[] = $childId;
            }
        }

        return $ids;
    }
}
