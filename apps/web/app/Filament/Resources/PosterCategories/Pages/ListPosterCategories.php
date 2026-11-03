<?php

namespace App\Filament\Resources\PosterCategories\Pages;

use App\Filament\Resources\PosterCategories\PosterCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosterCategories extends ListRecords
{
    protected static string $resource = PosterCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Добавить категорию')];
    }
}
