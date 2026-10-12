<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/**
 * Fields for the quick create/edit modals on the category tree page.
 *
 * The parent is never a field here: on the tree, position comes from the node
 * whose "add" button was clicked, or from the dedicated "move" action.
 */
class CategoryNodeForm
{
    /** @return array<int, Component> */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('اسم التصنيف')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                    // Only ever fills a blank slug, so an edited one is never
                    // overwritten and existing links keep working.
                    if (filled($state) && blank($get('slug'))) {
                        $set('slug', Str::slug($state));
                    }
                }),

            TextInput::make('slug')
                ->label('المعرّف في الرابط')
                ->required()
                ->maxLength(255)
                ->rules(['regex:/^[a-z0-9-]+$/'])
                ->unique(table: Category::class, column: 'slug', ignoreRecord: true)
                ->helperText('يظهر في رابط الصفحة: /category/slug'),

            Textarea::make('description')
                ->label('الوصف')
                ->rows(2),

            FileUpload::make('image')
                ->label('صورة التصنيف')
                ->image()
                ->imageEditor()
                ->directory('categories')
                ->disk('public'),

            Toggle::make('is_active')
                ->label('مُفعّل')
                ->default(true)
                ->helperText('إيقافه يخفيه من القائمة ومن الموقع، هو وكل ما تحته.'),
        ];
    }

    /**
     * The tree as select options, each label indented by its depth so the
     * hierarchy stays readable in a plain dropdown.
     *
     * A nested set comes back in pre-order, so one flat query is already in
     * tree order — no recursion needed here.
     *
     * @param  array<int, int>  $exclude  ids to leave out, subtrees included
     * @return array<int, string>
     */
    public static function parentOptions(array $exclude = []): array
    {
        return Category::query()
            ->withDepth()
            ->defaultOrder()
            ->get()
            ->reject(fn (Category $category) => in_array($category->getKey(), $exclude, true))
            ->mapWithKeys(fn (Category $category) => [
                $category->getKey() => str_repeat('—  ', (int) $category->depth).$category->name,
            ])
            ->all();
    }
}
