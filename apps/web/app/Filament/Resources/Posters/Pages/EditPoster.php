<?php

namespace App\Filament\Resources\Posters\Pages;

use App\Filament\Resources\Posters\PosterResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPoster extends EditRecord
{
    protected static string $resource = PosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')->label('Открыть на сайте')->url(fn (): string => route('posters.show', $this->record))->openUrlInNewTab()->visible(fn (): bool => $this->record->status === 'published'),
            DeleteAction::make(),
        ];
    }
}
