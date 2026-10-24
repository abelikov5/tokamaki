<?php

namespace App\Filament\Resources\Generations\Pages;

use App\Filament\Resources\Generations\GenerationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewGeneration extends ViewRecord
{
    protected static string $resource = GenerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openProject')
                ->label('Открыть проект')
                ->url(fn (): string => route('generations.resume', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
