<?php

namespace App\Filament\Resources\Posters\Pages;

use App\Filament\Resources\Posters\PosterResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPoster extends EditRecord
{
    protected static string $resource = PosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Опубликовать')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Опубликовать постер?')
                ->modalDescription('После публикации постер появится в каталоге сайта.')
                ->visible(fn (): bool => $this->record->status !== 'published')
                ->action(function (): void {
                    if (! $this->record->images()->exists()) {
                        Notification::make()
                            ->title('Нельзя опубликовать без фотографий')
                            ->body('Повторите импорт из WB или добавьте изображение в источник.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->record->update(['status' => 'published']);
                    $this->refreshFormData(['status']);

                    Notification::make()->title('Постер опубликован')->success()->send();
                }),
            Action::make('open')->label('Открыть на сайте')->url(fn (): string => route('posters.show', $this->record))->openUrlInNewTab()->visible(fn (): bool => $this->record->status === 'published'),
            DeleteAction::make(),
        ];
    }
}
