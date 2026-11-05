<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Resources\ContentPages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The storefront link, so a wording change can be checked in one
            // click rather than retyping the slug into the address bar.
            Action::make('view')
                ->label('عرض في المتجر')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => route('page', $this->getRecord()))
                ->openUrlInNewTab(),

            DeleteAction::make(),
        ];
    }
}
