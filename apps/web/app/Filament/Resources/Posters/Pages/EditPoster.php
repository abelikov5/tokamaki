<?php

namespace App\Filament\Resources\Posters\Pages;

use App\Filament\Resources\Posters\PosterResource;
use App\Jobs\GeneratePosterContentJob;
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
            Action::make('generateAiContent')
                ->label('Сгенерировать через ИИ')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Перегенерировать контент картины?')
                ->modalDescription('Название, описания, SEO, категория и поисковые параметры будут обновлены по изображению. Задача выполнится в фоне.')
                ->action(function (): void {
                    $this->record->update([
                        'ai_generation_status' => 'pending',
                        'ai_generation_error' => null,
                    ]);
                    GeneratePosterContentJob::dispatch($this->record->id);
                    $this->refreshFormData(['ai_generation_status', 'ai_generation_error']);

                    Notification::make()->title('Генерация поставлена в очередь')->success()->send();
                }),
            Action::make('publish')
                ->label('Опубликовать')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Опубликовать картину?')
                ->modalDescription('После публикации картина появится в каталоге сайта.')
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

                    Notification::make()->title('Картина опубликована')->success()->send();
                }),
            Action::make('open')->label('Открыть на сайте')->url(fn (): string => route('posters.show', $this->record))->openUrlInNewTab()->visible(fn (): bool => $this->record->status === 'published'),
            DeleteAction::make(),
        ];
    }
}
