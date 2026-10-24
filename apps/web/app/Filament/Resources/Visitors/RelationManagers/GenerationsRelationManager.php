<?php

namespace App\Filament\Resources\Visitors\RelationManagers;

use App\Filament\Resources\Generations\GenerationResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class GenerationsRelationManager extends RelationManager
{
    protected static string $relationship = 'generations';

    protected static ?string $relatedResource = GenerationResource::class;

    protected static ?string $title = 'Генерации посетителя';

    public function table(Table $table): Table
    {
        return $table;
    }
}
