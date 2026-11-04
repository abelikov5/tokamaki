<?php

namespace App\Filament\Resources\PosterImports\Tables;

use App\Models\PosterImport;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PosterImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
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
                IconColumn::make('only_new')->label('Только новые')->boolean(),
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
