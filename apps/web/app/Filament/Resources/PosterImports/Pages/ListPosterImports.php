<?php

namespace App\Filament\Resources\PosterImports\Pages;

use App\Filament\Resources\PosterImports\PosterImportResource;
use App\Models\IndexNowSubmission;
use App\Models\PosterImportSchedule;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPosterImports extends ListRecords
{
    protected static string $resource = PosterImportResource::class;

    public function getSubheading(): ?string
    {
        $submission = IndexNowSubmission::query()
            ->where('source', 'full')
            ->latest()
            ->first();

        if ($submission === null) {
            return 'Полная отправка сайта в IndexNow ещё не запускалась.';
        }

        $createdAt = $submission->created_at->timezone('Europe/Moscow')->format('d.m.Y H:i:s');

        if ($submission->status === 'failed') {
            return "Последняя полная отправка в IndexNow: ошибка {$createdAt}.";
        }

        return "Последняя полная отправка в IndexNow: {$submission->urls_count} URL, {$createdAt}.";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configureSchedule')
                ->label('Настроить автоимпорт')
                ->icon('heroicon-o-clock')
                ->modalHeading('Автоматический импорт картин')
                ->modalDescription(fn (): string => 'Время указано по Москве. Последний запуск: '.(
                    PosterImportSchedule::current()->last_run_at?->timezone('Europe/Moscow')->format('d.m.Y H:i') ?? 'ещё не запускался'
                ))
                ->fillForm(fn (): array => PosterImportSchedule::current()->only([
                    'enabled', 'source_user_id', 'scheduled_time', 'random_count', 'image_limit', 'only_new', 'generate_ai',
                ]))
                ->schema([
                    Toggle::make('enabled')
                        ->label('Автоимпорт включён'),
                    TimePicker::make('scheduled_time')
                        ->label('Время запуска (Москва)')
                        ->seconds(false)
                        ->required(),
                    TextInput::make('source_user_id')
                        ->label('User ID в mockup')
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('random_count')
                        ->label('Количество случайных картин')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(500)
                        ->required(),
                    TextInput::make('image_limit')
                        ->label('Фотографий на картину')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(10)
                        ->required(),
                    Toggle::make('only_new')
                        ->label('Только ещё не импортированные'),
                    Toggle::make('generate_ai')
                        ->label('Создавать описание через ИИ'),
                ])
                ->action(function (array $data): void {
                    PosterImportSchedule::current()->update([
                        'enabled' => (bool) ($data['enabled'] ?? false),
                        'source_user_id' => (int) $data['source_user_id'],
                        'scheduled_time' => (string) $data['scheduled_time'],
                        'timezone' => 'Europe/Moscow',
                        'random_count' => (int) $data['random_count'],
                        'image_limit' => (int) $data['image_limit'],
                        'only_new' => (bool) ($data['only_new'] ?? false),
                        'generate_ai' => (bool) ($data['generate_ai'] ?? false),
                    ]);

                    Notification::make()
                        ->title('Настройки автоимпорта сохранены')
                        ->success()
                        ->send();
                }),
        ];
    }
}
