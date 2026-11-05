<?php

namespace App\Filament\Resources\ContentPages;

use App\Filament\Resources\ContentPages\Pages\CreatePage;
use App\Filament\Resources\ContentPages\Pages\EditPage;
use App\Filament\Resources\ContentPages\Pages\ListPages;
use App\Filament\Resources\ContentPages\Schemas\PageForm;
use App\Filament\Resources\ContentPages\Tables\PagesTable;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The directory is `ContentPages`, not `Pages`: Filament reserves a resource's
 * own `Pages/` subdirectory for its List/Create/Edit classes, and a resource
 * named for the Page model would nest `Pages/Pages/ListPages.php`.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?string $navigationLabel = 'الصفحات';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'صفحة';

    protected static ?string $pluralModelLabel = 'الصفحات';

    protected static ?string $recordTitleAttribute = 'title';

    // Without this the URL inherits the directory name — /admin/content-pages/pages.
    protected static ?string $slug = 'pages';

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
