<?php

namespace App\Filament\Resources\Generations\Tables;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GenerationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('visitor.browser_id')
                    ->label('Посетитель')
                    ->formatStateUsing(fn (?string $state): string => $state ? Str::limit($state, 13, '…') : 'Старые данные')
                    ->searchable(),
                TextColumn::make('original_name')->label('Файл')->searchable()->limit(35),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('settings.colors')->label('Цветов'),
                TextColumn::make('settings.complexity')->label('Сложность'),
                TextColumn::make('edits_count')->label('Правок')->sortable(),
                TextColumn::make('created_at')->label('Создано')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('last_activity_at')->label('Активность')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'processing' => 'Обрабатывается',
                        'completed' => 'Готово',
                        'failed' => 'Ошибка',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('openProject')
                    ->label('Открыть')
                    ->url(fn ($record): string => route('generations.resume', $record))
                    ->openUrlInNewTab()
                    ->visible(fn ($record): bool => $record->status === 'completed' && filled($record->result_id)),
            ]);
    }
}
