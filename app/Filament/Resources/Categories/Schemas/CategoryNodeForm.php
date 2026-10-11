<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/**
 * Fields for the quick create/edit modals on the category tree page.
 *
 * The parent is never a field here: on the tree, position is set by the node
 * you clicked "add" on, or by the dedicated "move" action.
 */
class CategoryNodeForm
{
    /** @return array<int, \Filament\Schemas\Components\Component> */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('اسم التصنيف')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                    if ($operation !== 'edit' && filled($state)) {
                        $set('slug', Str::slug($state));
                    }
                }),

            TextInput::make('slug')
                ->label('المعرّف في الرابط')
                ->required()
                ->maxLength(255)
                ->rules(['regex:/^[a-z0-9-]+$/'])
                ->unique(
                    table: Category::class,
                    column: 'slug',
                    ignoreRecord: true,
                )
                ->helperText('يظهر في رابط الصفحة: /category/السلاج'),

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
     * Flat tree as select options, each label indented by its depth so the
     * hierarchy stays readable in a plain dropdown.
     *
     * @param  array<int, int>  $exclude
     * @return array<int, string>
     */
    public static function parentOptions(array $exclude = []): array
    {
        $options = [];

        $walk = function (iterable $nodes) use (&$walk, &$options, $exclude): void {
            foreach ($nodes as $node) {
                if (in_array($node->getKey(), $exclude, true)) {
                    continue;
                }

                $options[$node->getKey()] = str_repeat('—  ', $node->depth).$node->name;

                $walk($node->children);
            }
        };

        $walk(Category::tree());

        return $options;
    }
}
