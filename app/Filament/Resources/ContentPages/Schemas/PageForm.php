<?php

namespace App\Filament\Resources\ContentPages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('محتوى الصفحة')
                    ->schema([
                        TextInput::make('title')
                            ->label('عنوان الصفحة')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state) ?: Str::random(8));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('المعرّف في الرابط')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z0-9-]+$/'])
                            ->prefix(url('/page').'/')
                            ->helperText('العناوين العربية لا تُولّد معرّفاً تلقائياً — اكتبه بالإنجليزية، مثل about-us.'),

                        RichEditor::make('content')
                            ->label('المحتوى')
                            ->required()
                            // A deliberately short toolbar: these are policy
                            // and about pages, and the storefront only styles
                            // what is listed here (see .prose-shop in app.css).
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['h2', 'h3'],
                                ['blockquote', 'bulletList', 'orderedList'],
                                ['attachFiles'],
                                ['undo', 'redo'],
                            ])
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('pages')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('الظهور والأرشفة')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('منشورة')
                            ->helperText('إيقافها يُرجع 404 للزوار، ولا يكتفي بإخفاء الرابط.')
                            ->default(true),

                        Toggle::make('show_in_footer')
                            ->label('تظهر في تذييل الموقع')
                            ->default(true),

                        TextInput::make('menu_title')
                            ->label('الاسم المختصر في التذييل')
                            ->maxLength(255)
                            ->helperText('اتركه فارغاً لاستخدام عنوان الصفحة كما هو.'),

                        TextInput::make('sort_order')
                            ->label('ترتيب الظهور')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('الأصغر يظهر أولاً.'),

                        Textarea::make('meta_description')
                            ->label('وصف محركات البحث')
                            ->maxLength(255)
                            ->rows(2)
                            ->helperText('يُستخدم في نتائج البحث. يُشتق من أول المحتوى إن تُرك فارغاً.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
