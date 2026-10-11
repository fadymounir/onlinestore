<?php

namespace App\Filament\Resources\Attributes\Schemas;

use App\Enums\AttributeType;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الخصيصة')
                    ->description('الخصيصة هي ما يتغيّر به المنتج: اللون، المقاس، الخامة…')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم الخصيصة')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('مثال: اللون'),

                        TextInput::make('slug')
                            ->label('المعرّف البرمجي')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9-]+$/'])
                            ->placeholder('color')
                            ->helperText('بالإنجليزية — يُستخدم في الكود والواجهة.'),

                        ToggleButtons::make('type')
                            ->label('طريقة العرض')
                            ->options(AttributeType::options())
                            ->default(AttributeType::Select->value)
                            ->inline()
                            ->required()
                            ->helperText('«لون» يعرض دوائر ملوّنة للعميل، و«قائمة اختيار» يعرض أزرار نصية.'),

                        TextInput::make('sort_order')
                            ->label('ترتيب الظهور')
                            ->integer()
                            ->minValue(0)
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('مُفعّلة')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
