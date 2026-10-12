<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected static ?string $title = 'التصنيفات (جدول)';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('openTree')
                ->label('العرض كشجرة')
                ->icon(Heroicon::OutlinedRectangleGroup)
                ->color('gray')
                ->url(CategoryResource::getUrl('index')),
        ];
    }
}
