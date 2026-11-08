<?php

namespace App\Filament\Resources\PosterImports\Tables;

use App\Models\PosterImport;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class PosterImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('source')
                    ->label('Запуск')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'scheduled' ? 'Автоматически' : 'Вручную')
                    ->color(fn (string $state): string => $state === 'scheduled' ? 'info' : 'gray'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'В очереди',
                        'running' => 'Импортируется',
                        'completed' => 'Готово',
                        'completed_with_errors' => 'Готово с ошибками',
                        'failed' => 'Ошибка',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'completed_with_errors', 'running', 'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('selection_mode')
                    ->label('Режим')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'selected' => 'По nm_id',
                        'random' => 'Случайные',
                        default => 'Все',
                    }),
                TextColumn::make('progress')
                    ->label('Прогресс')
                    ->badge()
                    ->getStateUsing(fn (PosterImport $record): string => $record->processed_count.' / '.$record->total_count),
                TextColumn::make('random_count')->label('Запрошено')->placeholder('—'),
                TextColumn::make('image_limit')->label('Фото')->numeric(),
                IconColumn::make('generate_ai')->label('ИИ-описание')->boolean(),
                IconColumn::make('only_new')->label('Только новые')->boolean(),
                TextColumn::make('indexnow_status')
                    ->label('Индексация')
                    ->badge()
                    ->getStateUsing(function (PosterImport $record): string {
                        if (! $record->generate_ai) {
                            return 'Не запрошена';
                        }

                        if ((int) $record->indexnow_completed_count === 0 && (int) $record->indexnow_failed_count === 0) {
                            return 'Ожидает';
                        }

                        if ((int) $record->indexnow_failed_count > 0) {
                            return $record->indexnow_completed_count.' успешно · '.$record->indexnow_failed_count.' ошибок';
                        }

                        return $record->indexnow_completed_count.' картин · '.((int) $record->indexnow_urls_count).' URL';
                    })
                    ->color(fn (PosterImport $record): string => match (true) {
                        (int) $record->indexnow_failed_count > 0 => 'danger',
                        (int) $record->indexnow_completed_count > 0 => 'success',
                        default => 'gray',
                    })
                    ->tooltip(fn (PosterImport $record): ?string => $record->indexnow_last_submitted_at === null
                        ? null
                        : 'Последняя отправка: '.Carbon::parse($record->indexnow_last_submitted_at)->timezone('Europe/Moscow')->format('d.m.Y H:i:s')),
                TextColumn::make('created_count')->label('Создано')->numeric(),
                TextColumn::make('updated_count')->label('Обновлено')->numeric(),
                TextColumn::make('skipped_count')->label('Пропущено')->numeric(),
                TextColumn::make('failed_count')->label('Ошибки')->numeric()->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('errors')
                    ->label('Текст ошибок')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state)
                        ? collect($state)->map(fn (array $error): string => trim(($error['nm_id'] ?? '').' '.($error['message'] ?? '')))->join('; ')
                        : '')
                    ->wrap()
                    ->limit(120)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Создан')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('finished_at')->label('Завершён')->dateTime('d.m.Y H:i:s')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc');
    }
}
