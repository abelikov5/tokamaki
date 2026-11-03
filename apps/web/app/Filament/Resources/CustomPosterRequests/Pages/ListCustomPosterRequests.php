<?php

namespace App\Filament\Resources\CustomPosterRequests\Pages;

use App\Filament\Resources\CustomPosterRequests\CustomPosterRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomPosterRequests extends ListRecords
{
    protected static string $resource = CustomPosterRequestResource::class;
}
