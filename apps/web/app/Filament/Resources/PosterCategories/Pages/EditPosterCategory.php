<?php

namespace App\Filament\Resources\PosterCategories\Pages;

use App\Filament\Resources\PosterCategories\PosterCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPosterCategory extends EditRecord
{
    protected static string $resource = PosterCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
