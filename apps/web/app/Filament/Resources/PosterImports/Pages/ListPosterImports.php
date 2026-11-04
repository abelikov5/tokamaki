<?php

namespace App\Filament\Resources\PosterImports\Pages;

use App\Filament\Resources\PosterImports\PosterImportResource;
use Filament\Resources\Pages\ListRecords;

class ListPosterImports extends ListRecords
{
    protected static string $resource = PosterImportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
